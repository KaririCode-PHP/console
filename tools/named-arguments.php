<?php

declare(strict_types=1);

/**
 * Spec v4.3.3 §30: named arguments "at call sites with 3+ parameters or
 * ambiguous booleans". Reports offending calls; --fix inserts the parameter
 * names, resolved by reflection (PHPStan then validates every name).
 *
 *   php tools/named-arguments.php src          # exit 1 on violations
 *   php tools/named-arguments.php src --fix
 *
 * For a PHP built-in function the first argument (its subject) stays
 * positional; arguments bound to a variadic parameter cannot be named, and
 * invoking a user-supplied closure is exempt (its parameter names are unknown).
 */

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

// nikic/php-parser comes with the toolchain kcode installs (ADR-015).
require dirname(__DIR__) . '/.kcode/vendor/autoload.php';

$root = $argv[1] ?? 'src';
$fix  = in_array('--fix', $argv, true);

final class CallCollector extends NodeVisitorAbstract
{
    /** @var list<array{call: Node, class: ?string}> */
    public array $calls = [];

    private ?string $class = null;

    public function enterNode(Node $node): null
    {
        if ($node instanceof Node\Stmt\ClassLike && $node->namespacedName !== null) {
            $this->class = $node->namespacedName->toString();
        }

        if ($node instanceof Node\Expr\FuncCall || $node instanceof Node\Expr\New_
            || $node instanceof Node\Expr\StaticCall || $node instanceof Node\Expr\MethodCall
            || $node instanceof Node\Expr\NullsafeMethodCall) {
            $this->calls[] = ['call' => $node, 'class' => $this->class];
        }

        return null;
    }
}

/** @return list<ReflectionParameter>|null */
function parametersOf(Node $call, ?string $class): ?array
{
    try {
        if ($call instanceof Node\Expr\FuncCall && $call->name instanceof Node\Name) {
            $name = $call->name->getAttribute('namespacedName')?->toString() ?? $call->name->toString();
            $name = function_exists($name) ? $name : $call->name->getLast();

            return function_exists($name) ? (new ReflectionFunction($name))->getParameters() : null;
        }

        if ($call instanceof Node\Expr\New_ && $call->class instanceof Node\Name) {
            $target = resolveClass($call->class, $class);

            return $target === null ? null : ((new ReflectionClass($target))->getConstructor()?->getParameters() ?? []);
        }

        if ($call instanceof Node\Expr\StaticCall && $call->class instanceof Node\Name && $call->name instanceof Node\Identifier) {
            $target = resolveClass($call->class, $class);

            return $target === null ? null : (new ReflectionMethod($target, $call->name->toString()))->getParameters();
        }

        if (($call instanceof Node\Expr\MethodCall || $call instanceof Node\Expr\NullsafeMethodCall) && $call->name instanceof Node\Identifier) {
            $target = receiverClass($call->var, $class);

            return $target === null ? null : (new ReflectionMethod($target, $call->name->toString()))->getParameters();
        }
    } catch (ReflectionException) {
    }

    return null;
}

function resolveClass(Node\Name $name, ?string $class): ?string
{
    return match (strtolower($name->toString())) {
        'self', 'static' => $class,
        'parent'         => $class === null ? null : (get_parent_class($class) ?: null),
        default          => $name->toString(),
    };
}

/** `$this` or a typed property of `$this`; anything else is unresolved. */
function receiverClass(Node\Expr $receiver, ?string $class): ?string
{
    if ($receiver instanceof Node\Expr\Variable && $receiver->name === 'this') {
        return $class;
    }

    if ($class !== null && $receiver instanceof Node\Expr\PropertyFetch
        && $receiver->var instanceof Node\Expr\Variable && $receiver->var->name === 'this'
        && $receiver->name instanceof Node\Identifier && property_exists($class, $receiver->name->toString())) {
        $type = (new ReflectionProperty($class, $receiver->name->toString()))->getType();

        return $type instanceof ReflectionNamedType && !$type->isBuiltin() ? $type->getName() : null;
    }

    return null;
}

$parser   = (new ParserFactory())->createForHostVersion();
$found    = 0;
$fixed    = 0;
$unsolved = [];

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $code      = (string) file_get_contents($file->getPathname());
    $collector = new CallCollector();
    $traverser = new NodeTraverser(new NameResolver(), $collector);
    $traverser->traverse($parser->parse($code) ?? []);
    $inserts = [];

    foreach ($collector->calls as ['call' => $call, 'class' => $class]) {
        if ($call->isFirstClassCallable()) {
            continue; // `foo(...)` has no arguments to name
        }

        if ($call instanceof Node\Expr\FuncCall && !$call->name instanceof Node\Name) {
            continue; // a user-supplied closure: its parameter names are the caller's choice
        }

        $args = array_values(array_filter($call->getArgs(), static fn(Node\Arg $a): bool => !$a->unpack));

        if (count($args) !== count($call->getArgs())) {
            continue; // spread arguments cannot be named
        }

        $isBool   = static fn(Node\Arg $a): bool => $a->name === null && $a->value instanceof Node\Expr\ConstFetch
            && in_array(strtolower($a->value->name->toString()), ['true', 'false'], true);
        $builtin  = $call instanceof Node\Expr\FuncCall;
        $firstBool = array_find_key($args, $isBool);
        $from     = match (true) {
            count($args) >= 3 => $builtin ? 1 : 0,
            $firstBool !== null => $firstBool,
            default => null,
        };

        if ($from === null) {
            continue;
        }

        $params = parametersOf($call, $class);
        $where  = "{$file->getPathname()}:{$call->getStartLine()}";

        for ($i = $from, $n = count($args); $i < $n; $i++) {
            if ($args[$i]->name !== null) {
                continue;
            }

            $param = $params[$i] ?? null;

            if ($params !== null && ($param === null || $param->isVariadic())) {
                break; // variadic (sprintf, max…): nothing more can be named
            }

            $found++;

            if ($param === null) {
                $unsolved[$where] = $where;
                continue;
            }

            $inserts[$args[$i]->getStartFilePos()] = $param->getName() . ': ';
        }
    }

    if ($fix && $inserts !== []) {
        krsort($inserts);
        foreach ($inserts as $position => $prefix) {
            $code = substr($code, 0, $position) . $prefix . substr($code, $position);
            $fixed++;
        }
        file_put_contents($file->getPathname(), $code);
    }
}

foreach ($unsolved as $where) {
    fwrite(STDERR, "unresolved callee (name the arguments by hand): {$where}\n");
}

$remaining = $fix ? count($unsolved) : $found;
echo $fix ? "named-arguments: inserted {$fixed} names; " . count($unsolved) . " unresolved\n" : "named-arguments: {$found} positional argument(s) to name\n";
exit($remaining === 0 ? 0 : 1);
