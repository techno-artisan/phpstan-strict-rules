<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Tests\Rules\Data\LooseComparison;

/**
 * @param array<string, mixed> $list
 */
function flagged(mixed $a, mixed $b, ?string $value, array $list): void
{
    // --- positive cases: every loose comparison must be reported ---
    $r1 = $a == $b;            // loose ==
    $r2 = $a != $b;            // loose !=
    $r3 = $a <> $b;            // <> parses to the same NotEqual node, reported as !=
    $r4 = $value == null;      // uncompromising: == null is flagged too
    $r5 = 0 == 'foo';          // classic type-coercion bug
    if ($a == $b) {            // == inside a condition
        $a = $b;
    }
    $r6 = $list['k'] != $b;    // != on an array element

    // --- negative cases: must NOT be reported ---
    $s1 = $a === $b;           // strict equal
    $s2 = $a !== $b;           // strict not-equal
    $s3 = $value === null;     // strict null check is the correct alternative
    $s4 = $a < $b;             // ordering operators are not loose equality
    $s5 = $a > $b;
    $s6 = $a <= $b;
    $s7 = $a >= $b;
    $s8 = $a <=> $b;           // spaceship, not loose equality

    unset($r1, $r2, $r3, $r4, $r5, $r6, $s1, $s2, $s3, $s4, $s5, $s6, $s7, $s8);
}
