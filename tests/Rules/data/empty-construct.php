<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Tests\Rules\Data\EmptyConstruct;

final class Box
{
    /**
     * A method literally named empty() — this is a MethodCall node, NOT the
     * empty() language construct, and must therefore never be flagged.
     */
    public function empty(): bool
    {
        return true;
    }

    public static function emptyStatic(): bool
    {
        return false;
    }
}

/**
 * @param array<string, mixed> $list
 */
function flagged(?string $value, array $list, Box $box): void
{
    // --- positive cases: every empty() language construct must be reported ---
    $a = empty($value);
    $b = empty($list['key']);
    if (empty($value)) {
        $value = 'x';
    }
    $c = empty($value) ? 1 : 2;

    // --- negative cases: correct alternatives, must NOT be reported ---
    $d = isset($value);
    $e = $value === '';
    $f = $value === null;
    $g = $value === null || $value === '';
    $h = !$value;
    $i = $box->empty();
    $j = Box::emptyStatic();

    unset($a, $b, $c, $d, $e, $f, $g, $h, $i, $j);
}
