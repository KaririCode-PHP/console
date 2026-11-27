<?php

declare(strict_types=1);

namespace KaririCode\Console\Tests\Conformance;

use KaririCode\Console\Exception\ConsoleException;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * KaririCode Spec v4.3.3 / ARFA 1.3 rules (§29.2) as executable checks.
 *
 * Each test scans every type under src/ and reports all violations at
 * once, by name, so a failure says exactly what to fix (§18: "zero
 * tolerance; validated by conformance tests").
 */
#[CoversNothing]
final class SpecConformanceTest extends TestCase
{
    private const string SOURCE_DIR = __DIR__ . '/../../src';

    private const string ROOT_NAMESPACE = 'KaririCode\\Console\\';

    /** ADR-003: Console owns error codes 9000–9099. */
    private const int CODE_RANGE_START = 9000;

    private const int CODE_RANGE_END = 9099;

    // ── Files ────────────────────────────────────────────────────────────

    public function testEveryFileDeclaresStrictTypes(): void
    {
        self::assertNoViolations('missing declare(strict_types=1)', array_keys(array_filter(
            self::sources(),
            static fn(string $code): bool => !str_contains($code, 'declare(strict_types=1);'),
        )));
    }

    public function testDocblockPackageMatchesNamespace(): void
    {
        $violations = [];

        foreach (self::sources() as $path => $code) {
            preg_match('/^namespace ([^;]+);/m', $code, $namespace);
            preg_match('/@package\s+(\S+)/', $code, $package);

            if (($namespace[1] ?? null) !== ($package[1] ?? null)) {
                $violations[] = $path;
            }
        }

        self::assertNoViolations('@package differs from namespace', $violations);
    }

    /** ARFA 1.3 house style: `// ── Title ───…` markers are exactly 72 characters. */
    public function testSectionMarkersAreSeventyTwoCharacters(): void
    {
        $violations = [];

        foreach (self::sources() as $path => $code) {
            foreach (explode("\n", $code) as $number => $line) {
                if (str_starts_with(ltrim($line), '// ──') && mb_strlen(trim($line)) !== 72) {
                    $violations[] = "{$path}:" . ($number + 1);
                }
            }
        }

        self::assertNoViolations('section marker is not 72 characters', $violations);
    }

    /** §17: summaries state the domain role, not "Represents a X". */
    public function testSummariesDescribeRolesNotRepresentations(): void
    {
        self::assertNoViolations('summary starts with "Represents a"/"Executes the"', array_keys(array_filter(
            self::sources(),
            static fn(string $code): bool => preg_match('/\/\*\*\s*\n\s*\*\s*(Represents a|Executes the)\b/', $code) === 1,
        )));
    }

    // ── §18 Prohibited antipatterns ──────────────────────────────────────

    public function testNoInterfaceSuffix(): void
    {
        self::assertNoViolations('interface named *Interface', self::typesWhere(
            static fn(\ReflectionClass $t): bool => $t->isInterface() && str_ends_with($t->getShortName(), 'Interface'),
        ));
    }

    public function testNoVagueManagerHelperUtilNames(): void
    {
        self::assertNoViolations('vague type name', self::typesWhere(
            static fn(\ReflectionClass $t): bool => preg_match('/(Manager|Helper|Utils?)$/', $t->getShortName()) === 1,
        ));
    }

    public function testNoArraySerialisationMethods(): void
    {
        self::assertNoViolations('toArray/fromArray/jsonSerialize', self::typesWhere(
            static fn(\ReflectionClass $t): bool => $t->implementsInterface(\JsonSerializable::class)
                || array_any(['toArray', 'fromArray', 'jsonSerialize'], static fn(string $m): bool => $t->hasMethod($m)),
        ));
    }

    public function testNoMagicPropertyOrCallAccess(): void
    {
        self::assertNoViolations('magic __get/__set/__call', self::typesWhere(
            static fn(\ReflectionClass $t): bool => array_any(
                ['__get', '__set', '__call', '__callStatic'],
                static fn(string $m): bool => $t->hasMethod($m),
            ),
        ));
    }

    /** "Getters/setters without hooks": a method whose body only reads or writes a property. */
    public function testNoTrivialGettersOrSetters(): void
    {
        $violations = [];

        foreach (self::types() as $type) {
            foreach ($type->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() !== $type->getName() || $method->isAbstract() || $method->isInternal()) {
                    continue;
                }

                $name     = $method->getName();
                $isGetter = preg_match('/^(get|is|has)[A-Z]/', $name) === 1 && $method->getNumberOfParameters() === 0;
                $isSetter = preg_match('/^set[A-Z]/', $name) === 1 && $method->getNumberOfParameters() === 1;

                if (!$isGetter && !$isSetter) {
                    continue; // behaviour (e.g. CommandLoader::load()), not an accessor
                }

                $body = self::bodyOf($method);

                if (preg_match('/^\{return\$this->\w+;\}$/', $body) === 1 || preg_match('/^\{\$this->\w+=\$\w+;\}$/', $body) === 1) {
                    $violations[] = "{$type->getName()}::{$method->getName()}()";
                }
            }
        }

        self::assertNoViolations('trivial accessor (use a property or hook)', $violations);
    }

    public function testInheritanceDepthIsAtMostTwoWithinThePackage(): void
    {
        self::assertNoViolations('inheritance deeper than 2 package levels', self::typesWhere(
            static fn(\ReflectionClass $t): bool => self::packageDepth($t) > 2,
        ));
    }

    // ── §5 Type taxonomy, §30 language features ──────────────────────────

    public function testValueObjectsAndAttributesAreFinalReadonly(): void
    {
        self::assertNoViolations('value object / attribute not final readonly', self::typesWhere(
            static fn(\ReflectionClass $t): bool => (str_contains($t->getName(), '\\ValueObject\\') || str_contains($t->getName(), '\\Attribute\\'))
                && !($t->isFinal() && $t->isReadOnly()),
        ));
    }

    /**
     * §18 "Mutable state without private(set)": a public property may be
     * mutable only through a hook or because a contract declares it
     * settable (e.g. Output::$verbosity { get; set; }).
     */
    public function testPublicMutableStateIsControlled(): void
    {
        $violations = [];

        foreach (self::types() as $type) {
            if ($type->isInterface() || $type->isEnum()) {
                continue;
            }

            foreach ($type->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->getDeclaringClass()->getName() !== $type->getName()
                    || $property->isReadOnly() || $property->isStatic() || $property->hasHooks()
                    || $property->isPrivateSet() || $property->isProtectedSet()) {
                    continue;
                }

                $contractSetter = array_any(
                    $type->getInterfaces(),
                    static fn(\ReflectionClass $contract): bool => $contract->hasProperty($property->getName())
                        && $contract->getProperty($property->getName())->hasHook(\PropertyHookType::Set),
                );

                if (!$contractSetter) {
                    $violations[] = "{$type->getName()}::\${$property->getName()}";
                }
            }
        }

        self::assertNoViolations('uncontrolled public mutable property', $violations);
    }

    /**
     * Zero-argument get*() accessors are replaced by properties. The one
     * exception resolves configuration lazily, which Spec v4.3.3 §11 forbids inside
     * property hooks (no I/O or lazy initialisation in hooks).
     */
    public function testNoZeroArgumentGetters(): void
    {
        $allowed = [\KaririCode\Console\Integration\ConfiguratorBridge::class];

        $violations = [];

        foreach (self::types() as $type) {
            foreach ($type->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                if ($method->getDeclaringClass()->getName() === $type->getName()
                    && !$method->isInternal()
                    && preg_match('/^get[A-Z]/', $method->getName()) === 1
                    && $method->getNumberOfParameters() === 0
                    && !in_array($type->getName(), $allowed, true)) {
                    $violations[] = "{$type->getName()}::{$method->getName()}()";
                }
            }
        }

        self::assertNoViolations('zero-argument getter', $violations);
    }

    public function testConcreteClassesAreFinalUnlessExtendedInPackage(): void
    {
        $parents = array_filter(array_map(
            static fn(\ReflectionClass $t): ?string => $t->getParentClass() ? $t->getParentClass()->getName() : null,
            self::types(),
        ));

        self::assertNoViolations('open class with no subclass in the package', self::typesWhere(
            static fn(\ReflectionClass $t): bool => !$t->isInterface() && !$t->isEnum() && !$t->isAbstract()
                && !$t->isFinal() && !in_array($t->getName(), $parents, true),
        ));
    }

    /** Pure by default; backing needs an external contract, and Console has none. */
    public function testEnumsArePure(): void
    {
        self::assertNoViolations('backed enum', self::typesWhere(
            static fn(\ReflectionClass $t): bool => $t->isEnum() && enum_exists($t->getName()) && (new \ReflectionEnum($t->getName()))->isBacked(),
        ));
    }

    public function testClassConstantsAreTyped(): void
    {
        $violations = [];

        foreach (self::types() as $type) {
            foreach ($type->getReflectionConstants() as $constant) {
                if ($constant->getDeclaringClass()->getName() === $type->getName()
                    && !$constant->isEnumCase() && !$constant->hasType()) {
                    $violations[] = "{$type->getName()}::{$constant->getName()}";
                }
            }
        }

        self::assertNoViolations('untyped class constant', $violations);
    }

    public function testContractNamespaceHoldsOnlyInterfaces(): void
    {
        self::assertNoViolations('non-interface in Contract\\', self::typesWhere(
            static fn(\ReflectionClass $t): bool => str_contains($t->getName(), '\\Contract\\') && !$t->isInterface(),
        ));
    }

    // ── ADR-003 error codes, §28 zero dependencies ───────────────────────

    public function testEveryExceptionFactoryUsesTheAllocatedCodeRange(): void
    {
        $violations = [];
        $codes      = [];
        $factories  = array_filter(
            (new \ReflectionClass(ConsoleException::class))->getMethods(\ReflectionMethod::IS_STATIC),
            static fn(\ReflectionMethod $m): bool => $m->isPublic(),
        );

        foreach ($factories as $factory) {
            $arguments = array_map(
                static fn(\ReflectionParameter $p): ?string => $p->allowsNull() ? null : 'x',
                $factory->getParameters(),
            );
            $exception = $factory->invoke(null, ...$arguments);
            self::assertInstanceOf(ConsoleException::class, $exception);
            $code           = (int) $exception->getCode();
            $codes[$code][] = $factory->getName();

            if ($code < self::CODE_RANGE_START || $code > self::CODE_RANGE_END) {
                $violations[] = "{$factory->getName()}() → {$code}";
            }
        }

        foreach ($codes as $code => $owners) {
            if (count($owners) > 1) {
                $violations[] = "{$code} shared by " . implode(', ', $owners);
            }
        }

        self::assertNotSame([], $factories);
        self::assertNoViolations('error code outside 9000–9099 or not unique', $violations);
    }

    /**
     * Principle 16: no Composer dependency except PSR contracts; §31: sibling
     * components are reached only through kariricode/contract.
     */
    public function testRuntimeRequiresOnlyPhpExtensionsAndContracts(): void
    {
        /** @var array{require?: array<string, string>} $composer */
        $composer = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertNoViolations('runtime dependency that is not PHP, an extension, a PSR contract or kariricode/contract', array_values(array_filter(
            array_keys($composer['require'] ?? []),
            static fn(string $name): bool => $name !== 'php' && !str_starts_with($name, 'ext-')
                && !str_starts_with($name, 'psr/') && $name !== 'kariricode/contract',
        )));
    }

    /** §17 C4: one concrete usage example per public class (exceptions excluded). */
    public function testEveryClassShowsAUsageExample(): void
    {
        self::assertNoViolations('class docblock without a usage example', self::typesWhere(
            static fn(\ReflectionClass $t): bool => !$t->isInterface() && !$t->isEnum() && !$t->isSubclassOf(\Throwable::class)
                && preg_match('/\n\s*\*\s{3,}\S|```/', (string) $t->getDocComment()) !== 1,
        ));
    }

    /** §17 C3: types whose methods write, publish or call out say so. */
    public function testSideEffectsAreDocumented(): void
    {
        $sideEffecting = [
            'Core\\Application', 'Core\\CommandRegistry', 'Command\\HelpCommand', 'Command\\ListCommand',
            'Command\\CompletionCommand', 'Formatter\\ProgressBar', 'Formatter\\TableFormatter',
            'Output\\StreamOutput', 'Output\\ConsoleOutput', 'Output\\DiagnosticOutput', 'Prompt\\Prompter',
            'Middleware\\SignalMiddleware', 'Middleware\\LoggingMiddleware', 'Middleware\\EventMiddleware',
            'Middleware\\ObservabilityMiddleware', 'Loader\\AttributeCommandLoader', 'Integration\\ClassDiscoveryBridge',
            'Integration\\ConfiguratorBridge', 'Integration\\ObservabilityBridge',
        ];

        self::assertNoViolations('side-effecting type without a "Side effects:" note', array_values(array_filter(
            $sideEffecting,
            static fn(string $type): bool => !str_contains((string) (new \ReflectionClass(self::ROOT_NAMESPACE . $type))->getDocComment(), 'Side effects:'),
        )));
    }

    /** §30: named arguments at call sites with 3+ arguments or ambiguous booleans. */
    public function testCallSitesUseNamedArguments(): void
    {
        $root = (string) realpath(__DIR__ . '/../..');
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("{$root}/tools/named-arguments.php") . ' ' . escapeshellarg("{$root}/src") . ' 2>&1', $report, $status);

        self::assertSame(0, $status, implode("\n", $report));
    }

    public function testArfaConformanceLevelIsDeclared(): void
    {
        /** @var array{extra?: array{kariricode?: array{arfa?: array{version?: string, level?: int}, asvs?: array{version?: string, level?: int}, spec?: string}}} $composer */
        $composer = json_decode((string) file_get_contents(__DIR__ . '/../../composer.json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertSame(['version' => '1.3', 'level' => 1], $composer['extra']['kariricode']['arfa'] ?? null);
        self::assertSame(['version' => '5.0', 'level' => 1], $composer['extra']['kariricode']['asvs'] ?? null, 'G4 needs asvs.level too');
        self::assertSame('4.3.3', $composer['extra']['kariricode']['spec'] ?? null);
    }

    /** §29.2: no mutable static/global state. */
    public function testNoStaticProperties(): void
    {
        $violations = [];

        foreach (self::types() as $type) {
            foreach ($type->getProperties(\ReflectionProperty::IS_STATIC) as $property) {
                if ($property->getDeclaringClass()->getName() === $type->getName()) {
                    $violations[] = "{$type->getName()}::\${$property->getName()}";
                }
            }
        }

        self::assertNoViolations('static (shared mutable) property', $violations);
    }

    /** §29.2: all ADR/SPEC references intact — every ADR cited and every relative link resolves. */
    public function testDocumentReferencesResolve(): void
    {
        $root       = (string) realpath(__DIR__ . '/../..');
        $adrs       = array_map(static fn(string $f): string => substr(basename($f), 0, 7), glob("{$root}/docs/adr/ADR-*.md") ?: []);
        $documents  = [...(glob("{$root}/*.md") ?: []), ...(glob("{$root}/docs/**/*.md") ?: []), ...(glob("{$root}/docs/*.md") ?: [])];
        $violations = [];

        foreach ($documents as $document) {
            $text = (string) file_get_contents((string) $document);
            $name = substr((string) $document, strlen($root) + 1);

            preg_match_all('/\bADR-(\d{3})\b/', $text, $cited);
            foreach (array_unique($cited[0]) as $adr) {
                if (!in_array($adr, $adrs, true)) {
                    $violations[] = "{$name} cites missing {$adr}";
                }
            }

            preg_match_all('/\]\((?!https?:|#)([^)#]+)\)/', $text, $links);
            foreach ($links[1] as $link) {
                if (!file_exists(dirname((string) $document) . '/' . $link)) {
                    $violations[] = "{$name} links to missing {$link}";
                }
            }
        }

        self::assertNotSame([], $adrs);
        self::assertNoViolations('broken document reference', $violations);
    }

    /**
     * ASVS 5.0 V1.3.2 / V1.2.5: no dynamic code execution, and OS calls only
     * where intended (stty in Prompter, arguments escapeshellarg()'d).
     */
    public function testNoDynamicCodeExecutionOrUnexpectedShellCalls(): void
    {
        $allowed    = ['Prompt/Prompter.php' => ['shell_exec']];
        $violations = [];

        foreach (self::sources() as $path => $code) {
            $tokens = token_get_all($code);

            foreach ($tokens as $index => $token) {
                if (is_array($token) && in_array($token[0], [T_EVAL], true)) {
                    $violations[] = "{$path}: eval";
                }

                if ($token === '`') {
                    $violations[] = "{$path}: backtick execution";
                }

                if (is_array($token) && $token[0] === T_STRING
                    && in_array(strtolower($token[1]), ['exec', 'system', 'passthru', 'shell_exec', 'proc_open', 'popen', 'create_function', 'assert'], true)
                    && ($tokens[$index + 1] ?? null) === '('
                    && !in_array(strtolower($token[1]), $allowed[str_replace('\\', '/', $path)] ?? [], true)) {
                    $violations[] = "{$path}: {$token[1]}()";
                }
            }
        }

        self::assertNoViolations('dynamic code execution or unexpected OS call', array_values(array_unique($violations)));
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** @param list<string> $violations */
    private static function assertNoViolations(string $rule, array $violations): void
    {
        self::assertSame([], $violations, "{$rule}:\n  " . implode("\n  ", $violations));
    }

    /**
     * @param \Closure(\ReflectionClass<object>): bool $predicate
     * @return list<string>
     */
    private static function typesWhere(\Closure $predicate): array
    {
        return array_values(array_map(
            static fn(\ReflectionClass $t): string => $t->getName(),
            array_filter(self::types(), $predicate),
        ));
    }

    /** @return array<string, string> relative path => source */
    private static function sources(): array
    {
        $sources  = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::SOURCE_DIR, \FilesystemIterator::SKIP_DOTS));

        /** @var \SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') {
                $relative           = substr((string) $file->getRealPath(), strlen((string) realpath(self::SOURCE_DIR)) + 1);
                $sources[$relative] = (string) file_get_contents((string) $file->getRealPath());
            }
        }

        ksort($sources);

        return $sources;
    }

    /** @return list<\ReflectionClass<object>> */
    private static function types(): array
    {
        $types = [];

        foreach (array_keys(self::sources()) as $relative) {
            $name = self::ROOT_NAMESPACE . str_replace('/', '\\', substr($relative, 0, -4));

            if (class_exists($name) || interface_exists($name) || enum_exists($name) || trait_exists($name)) {
                $types[] = new \ReflectionClass($name);
            }
        }

        return $types;
    }

    /** @param \ReflectionClass<object> $type */
    private static function packageDepth(\ReflectionClass $type): int
    {
        $depth = 0;

        for ($parent = $type->getParentClass(); $parent !== false; $parent = $parent->getParentClass()) {
            if (str_starts_with($parent->getName(), self::ROOT_NAMESPACE)) {
                $depth++;
            }
        }

        return $depth;
    }

    private static function bodyOf(\ReflectionMethod $method): string
    {
        $lines = file((string) $method->getFileName()) ?: [];
        $code  = implode('', array_slice($lines, (int) $method->getStartLine() - 1, (int) $method->getEndLine() - (int) $method->getStartLine() + 1));
        $body  = substr($code, (int) strpos($code, '{'));

        return (string) preg_replace('/\s+/', '', $body);
    }
}
