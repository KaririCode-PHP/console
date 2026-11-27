<?php

declare(strict_types=1);

/**
 * Spec v4.3.3 §13 complexity budgets (cognitive complexity is enforced by
 * PHPStan via tomasvotruba/cognitive-complexity):
 *
 *   cyclomatic complexity / method  <= 10   (McCabe, "modified" variant: a switch/match counts
 *                                            as one decision — §13 deems "a flat 12-arm match"
 *                                            acceptable; closures count toward their method)
 *   nesting depth / method          <= 3
 *   parameters / method             <= 4    (exempt: constructors of readonly classes whose
 *                                            parameters are all promoted — the object is the
 *                                            parameter object)
 *   public methods / class          <= 10   (soft: reported, not failing)
 *
 * Usage: php tools/complexity.php src   — exit 1 on any hard violation.
 */

use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

const MAX_CYCLOMATIC = 10;
const MAX_NESTING    = 3;
const MAX_PARAMETERS = 4;
const MAX_PUBLIC     = 10;

$decisions = [
    Node\Stmt\If_::class, Node\Stmt\ElseIf_::class, Node\Stmt\For_::class, Node\Stmt\Foreach_::class,
    Node\Stmt\While_::class, Node\Stmt\Do_::class, Node\Stmt\Catch_::class, Node\Expr\Ternary::class,
    Node\Expr\BinaryOp\Coalesce::class, Node\Expr\BinaryOp\BooleanAnd::class, Node\Expr\BinaryOp\BooleanOr::class,
    Node\Expr\BinaryOp\LogicalAnd::class, Node\Expr\BinaryOp\LogicalOr::class, Node\Expr\BinaryOp\LogicalXor::class,
];
$nesters = [
    Node\Stmt\If_::class, Node\Stmt\For_::class, Node\Stmt\Foreach_::class, Node\Stmt\While_::class,
    Node\Stmt\Do_::class, Node\Stmt\Switch_::class, Node\Stmt\TryCatch::class, Node\Expr\Match_::class,
];

$cyclomatic = static function (Node $method) use ($decisions): int {
    $count = 1;
    foreach ((new NodeFinder())->find((array) $method->getStmts(), static fn(Node $n): bool => true) as $node) {
        if (in_array($node::class, $decisions, true)
            || $node instanceof Node\Stmt\Switch_ || $node instanceof Node\Expr\Match_) {
            $count++;
        }
    }
    return $count;
};

$nesting = static function (array $nodes, int $depth = 0) use (&$nesting, $nesters): int {
    $max = $depth;
    foreach ($nodes as $node) {
        if (!$node instanceof Node) {
            continue;
        }
        $inner = in_array($node::class, $nesters, true) ? $depth + 1 : $depth;
        $max   = max($max, $inner);
        foreach ($node->getSubNodeNames() as $name) {
            $child = $node->{$name};
            $max   = max($max, $nesting(is_array($child) ? $child : [$child], $inner));
        }
    }
    return $max;
};

$parser    = (new ParserFactory())->createForHostVersion();
$violations = [];
$warnings   = [];
$files      = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($argv[1] ?? 'src', FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }
    $ast = $parser->parse((string) file_get_contents($file->getPathname())) ?? [];

    foreach ((new NodeFinder())->findInstanceOf($ast, Node\Stmt\ClassLike::class) as $class) {
        $name   = $class->name?->toString() ?? '(anonymous)';
        $public = 0;

        foreach ($class->getMethods() as $method) {
            $where = "{$file->getPathname()}: {$name}::{$method->name}()";
            $public += $method->isPublic() ? 1 : 0;

            $ccn = $cyclomatic($method);
            if ($ccn > MAX_CYCLOMATIC) {
                $violations[] = "{$where} cyclomatic {$ccn} > " . MAX_CYCLOMATIC;
            }

            $depth = $nesting((array) $method->getStmts());
            if ($depth > MAX_NESTING) {
                $violations[] = "{$where} nesting {$depth} > " . MAX_NESTING;
            }

            $params          = count($method->params);
            $promotedOnlyVo  = $method->name->toString() === '__construct'
                && $class instanceof Node\Stmt\Class_ && $class->isReadonly()
                && array_all($method->params, static fn(Node\Param $p): bool => $p->flags !== 0);
            if ($params > MAX_PARAMETERS && !$promotedOnlyVo) {
                $violations[] = "{$where} parameters {$params} > " . MAX_PARAMETERS;
            }
        }

        if ($public > MAX_PUBLIC) {
            $warnings[] = "{$file->getPathname()}: {$name} has {$public} public methods (soft budget " . MAX_PUBLIC . ')';
        }
    }
}

foreach ($warnings as $warning) {
    fwrite(STDERR, "warning: {$warning}\n");
}
foreach ($violations as $violation) {
    fwrite(STDERR, "violation: {$violation}\n");
}
echo count($violations) === 0 ? "complexity: within §13 budgets\n" : 'complexity: ' . count($violations) . " violation(s)\n";
exit(count($violations) === 0 ? 0 : 1);
