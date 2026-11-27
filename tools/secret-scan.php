<?php

declare(strict_types=1);

/**
 * Spec v4.3.3 §24 "secret-scanning is clean": fails on high-confidence
 * credential patterns in versioned files (vendor/ excluded).
 * Usage: php tools/secret-scan.php   — exit 1 on any finding.
 */

const PATTERNS = [
    'private key block'  => '/-----BEGIN (?:RSA |EC |OPENSSH |DSA |PGP )?PRIVATE KEY-----/',
    'AWS access key id'  => '/\b(?:AKIA|ASIA)[0-9A-Z]{16}\b/',
    'GitHub token'       => '/\bgh[pousr]_[A-Za-z0-9]{36,}\b/',
    'Slack token'        => '/\bxox[baprs]-[A-Za-z0-9-]{10,}\b/',
    'Stripe live key'    => '/\b[sr]k_live_[A-Za-z0-9]{20,}\b/',
    'Google API key'     => '/\bAIza[0-9A-Za-z_\-]{35}\b/',
    'Packagist token'    => '/\bpkg_[A-Za-z0-9]{40,}\b/',
];

$root     = dirname(__DIR__);
$files    = new RecursiveIteratorIterator(new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    static fn(SplFileInfo $f): bool => !in_array($f->getFilename(), ['vendor', '.git', '.phpunit.cache'], true),
));
$findings = [];

foreach ($files as $file) {
    if (!$file->isFile() || $file->getSize() > 1_000_000) {
        continue;
    }

    $text = (string) file_get_contents($file->getPathname());

    foreach (PATTERNS as $label => $pattern) {
        if (preg_match($pattern, $text) === 1) {
            $findings[] = substr($file->getPathname(), strlen($root) + 1) . ": {$label}";
        }
    }
}

foreach ($findings as $finding) {
    fwrite(STDERR, "secret: {$finding}\n");
}

echo $findings === [] ? "secret-scan: clean\n" : 'secret-scan: ' . count($findings) . " finding(s)\n";
exit($findings === [] ? 0 : 1);
