<?php

declare(strict_types=1);

/**
 * Spec v4.3.3 §24 "SBOM generated": writes sbom.cdx.json (CycloneDX 1.5)
 * from composer.json/composer.lock. Runtime has no package dependencies;
 * development packages are listed with scope "excluded" (never shipped).
 */

$root     = dirname(__DIR__);
$manifest = json_decode((string) file_get_contents("{$root}/composer.json"), true, flags: JSON_THROW_ON_ERROR);
$lock     = json_decode((string) file_get_contents("{$root}/composer.lock"), true, flags: JSON_THROW_ON_ERROR);

$component = static fn(array $package, string $scope): array => [
    'type'    => 'library',
    'bom-ref' => "pkg:composer/{$package['name']}@{$package['version']}",
    'name'    => $package['name'],
    'version' => $package['version'],
    'purl'    => "pkg:composer/{$package['name']}@{$package['version']}",
    'scope'   => $scope,
];

$sbom = [
    'bomFormat'   => 'CycloneDX',
    'specVersion' => '1.5',
    'version'     => 1,
    'metadata'    => [
        'component' => ['type' => 'library', 'name' => $manifest['name'], 'bom-ref' => "pkg:composer/{$manifest['name']}"],
        'properties' => [['name' => 'runtime-requirements', 'value' => implode(', ', array_map(
            static fn(string $name, string $constraint): string => "{$name} {$constraint}",
            array_keys($manifest['require']),
            array_values($manifest['require']),
        ))]],
    ],
    'components' => [
        ...array_map(static fn(array $p): array => $component($p, 'required'), $lock['packages'] ?? []),
        ...array_map(static fn(array $p): array => $component($p, 'excluded'), $lock['packages-dev'] ?? []),
    ],
];

file_put_contents("{$root}/sbom.cdx.json", json_encode($sbom, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
printf("sbom: %d runtime, %d development components -> sbom.cdx.json\n", count($lock['packages'] ?? []), count($lock['packages-dev'] ?? []));
