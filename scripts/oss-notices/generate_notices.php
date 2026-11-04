<?php
/**
 * Generates THIRD-PARTY-NOTICES.txt for all production Composer packages
 * listed in composer.lock. Extracts verbatim LICENSE and NOTICE file contents
 * (Apache 2.0 requires NOTICE reproduction on distribution).
 *
 * Usage:
 *   php scripts/oss-notices/generate_notices.php --out-dir .
 *   php scripts/oss-notices/generate_notices.php --mode scan
 *
 * Exits non-zero if any production dep's license is not on the allowlist.
 * Reads composer.lock directly — no composer binary required at scan time.
 */

declare(strict_types=1);

function parse_args(array $argv): array {
    $out = [
        'out-dir' => '.',
        'out-filename' => 'THIRD-PARTY-NOTICES',
        'allowlist' => __DIR__ . '/allowed-licenses.json',
        'mode' => 'generate',
        'project-root' => dirname(__DIR__, 2),
    ];
    for ($i = 1; $i < count($argv); $i++) {
        $a = $argv[$i];
        if (!str_starts_with($a, '--')) continue;
        $eq = strpos($a, '=');
        if ($eq !== false) {
            $key = substr($a, 2, $eq - 2);
            $val = substr($a, $eq + 1);
        } else {
            $key = substr($a, 2);
            $val = $argv[$i + 1] ?? '';
            $i++;
        }
        $out[$key] = $val;
    }
    if (!in_array($out['mode'], ['scan', 'generate'], true)) {
        fwrite(STDERR, "Invalid --mode: {$out['mode']}. Use 'scan' or 'generate'.\n");
        exit(2);
    }
    return $out;
}

function load_json(string $path): array {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing file: {$path}\n");
        exit(2);
    }
    $data = json_decode(file_get_contents($path), true);
    if (!is_array($data)) {
        fwrite(STDERR, "Invalid JSON in {$path}\n");
        exit(2);
    }
    return $data;
}

function build_override_patterns(array $overrides): array {
    $patterns = [];
    foreach ($overrides as $key => $value) {
        if (!is_string($key) || !str_contains($key, '*') || !is_array($value)) continue;
        $escaped = preg_quote($key, '#');
        $regex = '#^' . str_replace('\*', '[^@]*', $escaped) . '$#';
        $patterns[] = ['regex' => $regex, 'value' => $value];
    }
    return $patterns;
}

function find_override(string $name, string $version, array $overrides, array $patterns): array {
    $key = "{$name}@{$version}";
    if (isset($overrides[$key]) && is_array($overrides[$key])) return $overrides[$key];
    foreach ($patterns as $p) {
        if (preg_match($p['regex'], $key)) return $p['value'];
    }
    return [];
}

function normalize_license(string $raw, array $aliases, array $allowed): string {
    $s = trim($raw);
    if ($s === '') return 'UNKNOWN';
    $s = trim($s, '()');
    $s = trim($s);
    if (strtoupper($s) === 'UNKNOWN') return 'UNKNOWN';
    if (preg_match('/\s+OR\s+/i', $s)) {
        $parts = preg_split('/\s+OR\s+/i', $s);
        $normalized = array_map(fn($p) => normalize_license($p, $aliases, $allowed), $parts);
        foreach ($normalized as $n) {
            if (in_array($n, $allowed, true)) return $n;
        }
        return $normalized[0] ?? 'UNKNOWN';
    }
    return $aliases[$s] ?? $s;
}

/**
 * Composer's `license` field is either:
 *   - a string like "MIT" or "(MIT OR Apache-2.0)"
 *   - an array like ["MIT"] or ["BSD-3-Clause", "GPL-2.0-only", "GPL-3.0-only"]
 * Per Composer convention, an array with multiple entries is a DISJUNCTION (OR).
 * We pick the first allowlisted entry, mirroring SPDX "OR" semantics.
 */
function resolve_composer_license(mixed $field, array $aliases, array $allowed): array {
    if (is_string($field)) {
        return ['raw' => $field, 'license' => normalize_license($field, $aliases, $allowed)];
    }
    if (is_array($field) && count($field) > 0) {
        $raw = implode(' OR ', array_map('strval', $field));
        foreach ($field as $lic) {
            $norm = normalize_license((string)$lic, $aliases, $allowed);
            if (in_array($norm, $allowed, true)) return ['raw' => $raw, 'license' => $norm];
        }
        return ['raw' => $raw, 'license' => normalize_license((string)$field[0], $aliases, $allowed)];
    }
    return ['raw' => 'UNKNOWN', 'license' => 'UNKNOWN'];
}

function first_matching_file(string $dir, array $patterns): ?array {
    if (!is_dir($dir)) return null;
    $entries = @scandir($dir) ?: [];
    foreach ($patterns as $pattern) {
        foreach ($entries as $name) {
            if ($name === '.' || $name === '..') continue;
            if (preg_match($pattern, $name)) {
                $path = $dir . DIRECTORY_SEPARATOR . $name;
                if (is_file($path)) {
                    $content = @file_get_contents($path);
                    if ($content !== false) return ['name' => $name, 'content' => $content];
                }
            }
        }
    }
    return null;
}

function gather(array $packages, string $vendorDir, array $overrides, array $overridePatterns, array $aliases, array $allowed): array {
    $LICENSE_PATTERNS = ['/^LICENSE(\..+)?$/i', '/^LICENCE(\..+)?$/i', '/^COPYING(\..+)?$/i'];
    $NOTICE_PATTERNS = ['/^NOTICE(\..+)?$/i'];
    $out = [];
    foreach ($packages as $pkg) {
        if (!is_array($pkg)) continue;
        $name = $pkg['name'] ?? null;
        $version = $pkg['version'] ?? '0';
        if (!$name) continue;
        $override = find_override($name, $version, $overrides, $overridePatterns);
        if (isset($override['license'])) {
            $raw = (string)$override['license'];
            $license = normalize_license($raw, $aliases, $allowed);
        } else {
            $resolved = resolve_composer_license($pkg['license'] ?? null, $aliases, $allowed);
            $raw = $resolved['raw'];
            $license = $resolved['license'];
        }
        $pkgDir = $vendorDir . DIRECTORY_SEPARATOR . $name;
        $licenseFile = first_matching_file($pkgDir, $LICENSE_PATTERNS);
        $noticeFile = first_matching_file($pkgDir, $NOTICE_PATTERNS);
        $authors = $pkg['authors'] ?? [];
        $copyright = null;
        if (is_array($authors) && isset($authors[0]) && is_array($authors[0])) {
            $a = $authors[0];
            $copyright = ($a['name'] ?? '') . (!empty($a['email']) ? " <{$a['email']}>" : '');
            $copyright = trim($copyright) ?: null;
        }
        $source = $pkg['source']['url'] ?? $pkg['dist']['url'] ?? null;
        $out[] = [
            'name' => $name,
            'version' => $version,
            'license' => $license,
            'rawLicense' => $raw,
            'copyright' => $copyright,
            'repository' => $source,
            'homepage' => $pkg['homepage'] ?? null,
            'description' => $pkg['description'] ?? null,
            'licenseText' => $licenseFile['content'] ?? null,
            'noticeText' => $noticeFile['content'] ?? null,
            'override' => $override,
        ];
    }
    usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']) ?: strcmp($a['version'], $b['version']));
    return $out;
}

function check_allowlist(array $enriched, array $allowed): array {
    $violations = [];
    $warnings = [];
    $now = new DateTimeImmutable('now');
    foreach ($enriched as $d) {
        $override = $d['override'] ?? [];
        if (!empty($override['exempt'])) {
            $until = $override['exemptUntil'] ?? null;
            $expired = false;
            if ($until) {
                try {
                    $expired = (new DateTimeImmutable($until)) < $now;
                } catch (Throwable $e) {
                    $expired = false;
                }
            }
            $reason = $override['reason'] ?? '';
            $suffix = $expired ? " EXEMPTION EXPIRED {$until}" : ($until ? " until {$until}" : '');
            $warnings[] = "{$d['name']}@{$d['version']}: exempt (license '{$d['license']}')" . $suffix . ". " . $reason;
            if ($expired) {
                $violations[] = ['dep' => $d, 'reason' => "Exemption for '{$d['license']}' expired on {$until}"];
            }
            continue;
        }
        $license = $d['license'];
        if ($license === 'UNKNOWN') {
            $violations[] = ['dep' => $d, 'reason' => 'License metadata missing from composer.lock'];
            continue;
        }
        if (preg_match('/\s+AND\s+/i', $license)) {
            $parts = array_map(fn($p) => trim(trim($p), '()'), preg_split('/\s+AND\s+/i', $license));
            $bad = array_filter($parts, fn($p) => !in_array($p, $allowed, true));
            if (!empty($bad)) {
                $violations[] = ['dep' => $d, 'reason' => 'AND expression contains disallowed: ' . implode(', ', $bad)];
            }
            continue;
        }
        if (!in_array($license, $allowed, true)) {
            $violations[] = ['dep' => $d, 'reason' => "License '{$license}' not on allowlist"];
        }
    }
    return ['violations' => $violations, 'warnings' => $warnings];
}

function build_txt(array $enriched): string {
    $out = [];
    $out[] = 'THIRD-PARTY SOFTWARE NOTICES AND INFORMATION';
    $out[] = str_repeat('=', 45);
    $out[] = '';
    $out[] = 'This product incorporates material from the Composer packages listed below.';
    $out[] = 'The original copyright notices and the licenses under which the copyright';
    $out[] = 'holders made the corresponding source code available are reproduced below.';
    $out[] = '';
    $out[] = 'Total components: ' . count($enriched);
    $out[] = 'Generated: ' . (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('c');
    $out[] = '';
    foreach ($enriched as $d) {
        $out[] = str_repeat('-', 71);
        $out[] = "{$d['name']}@{$d['version']}";
        if (!empty($d['copyright'])) $out[] = "Copyright: {$d['copyright']}";
        if (!empty($d['repository'])) $out[] = "Repository: {$d['repository']}";
        elseif (!empty($d['homepage'])) $out[] = "Homepage: {$d['homepage']}";
        $rawSuffix = ($d['rawLicense'] && $d['rawLicense'] !== $d['license']) ? " (raw: {$d['rawLicense']})" : '';
        $out[] = "License: {$d['license']}{$rawSuffix}";
        $out[] = '';
        if (!empty($d['licenseText'])) {
            $out[] = '--- LICENSE ---';
            $out[] = trim($d['licenseText']);
            $out[] = '';
        } else {
            $out[] = '[No LICENSE file bundled with this package]';
            $out[] = '';
        }
        if (!empty($d['noticeText'])) {
            $out[] = '--- NOTICE ---';
            $out[] = trim($d['noticeText']);
            $out[] = '';
        }
    }
    return implode("\n", $out);
}

function main(array $argv): void {
    $args = parse_args($argv);
    $allowlist = load_json($args['allowlist']);
    $allowed = $allowlist['allowed'] ?? [];
    $aliases = $allowlist['aliases'] ?? [];
    $overrides = $allowlist['manualOverrides'] ?? [];
    $overridePatterns = build_override_patterns($overrides);

    $projectRoot = realpath($args['project-root']) ?: $args['project-root'];
    $lockPath = $projectRoot . DIRECTORY_SEPARATOR . 'composer.lock';
    if (!is_file($lockPath)) {
        fwrite(STDERR, "Missing composer.lock at {$lockPath}\n");
        exit(2);
    }
    $lock = load_json($lockPath);
    $packages = $lock['packages'] ?? [];
    $vendorDir = $projectRoot . DIRECTORY_SEPARATOR . 'vendor';

    $allEnriched = gather($packages, $vendorDir, $overrides, $overridePatterns, $aliases, $allowed);
    $excluded = array_values(array_filter($allEnriched, fn($d) => !empty($d['override']['exclude'])));
    $enriched = array_values(array_filter($allEnriched, fn($d) => empty($d['override']['exclude'])));

    ['violations' => $violations, 'warnings' => $warnings] = check_allowlist($enriched, $allowed);

    if (!empty($warnings)) {
        fwrite(STDERR, "\nOSS license warnings:\n");
        foreach ($warnings as $w) fwrite(STDERR, "  - {$w}\n");
    }

    if (!empty($violations)) {
        $n = count($violations);
        fwrite(STDERR, "\nOSS license check FAILED: {$n} violation(s).\n\n");
        foreach ($violations as $v) {
            fwrite(STDERR, "  - {$v['dep']['name']}@{$v['dep']['version']}: {$v['reason']}\n");
        }
        $sortedAllowed = $allowed;
        sort($sortedAllowed);
        fwrite(STDERR, "\nAllowed: " . implode(', ', $sortedAllowed) . "\n");
        fwrite(STDERR, "Allowlist file: {$args['allowlist']}\n\n");
        exit(1);
    }

    if ($args['mode'] === 'scan') {
        $count = count($enriched);
        echo "OSS license check passed: {$count} production components on allowlist.\n";
        if (!empty($excluded)) {
            $names = implode(', ', array_map(fn($d) => $d['name'], $excluded));
            echo "Excluded (non-OSS / commercial) deps: " . count($excluded) . " ({$names})\n";
        }
        return;
    }

    $outDir = $args['out-dir'];
    if (!is_dir($outDir)) {
        if (!mkdir($outDir, 0755, true) && !is_dir($outDir)) {
            fwrite(STDERR, "Failed to create out-dir: {$outDir}\n");
            exit(2);
        }
    }
    $txtPath = rtrim($outDir, '/\\') . DIRECTORY_SEPARATOR . $args['out-filename'] . '.txt';
    file_put_contents($txtPath, build_txt($enriched));
    $count = count($enriched);
    $withNotice = count(array_filter($enriched, fn($d) => !empty($d['noticeText'])));
    echo "Wrote {$txtPath}\n";
    echo "Components: {$count}\n";
    echo "Components with NOTICE files: {$withNotice}\n";
    if (!empty($excluded)) {
        echo "Excluded from OSS notices (commercial/non-OSS): " . count($excluded) . "\n";
    }
}

main($argv);
