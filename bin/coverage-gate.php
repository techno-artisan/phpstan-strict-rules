<?php

declare(strict_types=1);

$path = $argv[1] ?? 'coverage.xml';

if (!is_file($path) || !is_readable($path)) {
    fwrite(STDERR, "coverage-gate: cannot read Clover file '{$path}'\n");
    exit(1);
}

$xml = simplexml_load_file($path);

if ($xml === false) {
    fwrite(STDERR, "coverage-gate: failed to parse Clover file '{$path}'\n");
    exit(1);
}

$metrics = $xml->xpath('/coverage/project/metrics');

if ($metrics === false || $metrics === []) {
    fwrite(STDERR, "coverage-gate: no /coverage/project/metrics element in '{$path}'\n");
    exit(1);
}

$statements = (int) $metrics[0]['statements'];
$covered = (int) $metrics[0]['coveredstatements'];

$percentage = $statements === 0 ? 100.0 : ($covered / $statements) * 100;

printf("Line coverage: %.2f%% (%d/%d statements)\n", $percentage, $covered, $statements);

if ($percentage < 100.0) {
    $uncovered = $statements - $covered;
    fwrite(STDERR, "coverage-gate: coverage below 100% — {$uncovered} uncovered statement(s)\n");
    exit(1);
}

exit(0);
