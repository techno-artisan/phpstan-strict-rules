<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Tests\Rules\Data\LooseInArray;

const MY_CONST = true;

final class Haystack
{
    /**
     * A method literally named in_array() — this is a MethodCall node, NOT the
     * global in_array() function, and must therefore never be flagged.
     */
    public function in_array(mixed $needle): bool
    {
        return true;
    }
}

/**
 * @param list<mixed> $list
 * @param array<string, mixed> $arr
 * @param array<int, mixed> $args
 */
function flagged(mixed $x, array $list, array $arr, Haystack $haystack, bool $flag, array $args): void
{
    // --- positive cases: loose array search must be reported ---
    in_array($x, $list);                 // strict missing
    in_array($x, $list, false);          // not true
    in_array($x, $list, $flag);          // not a literal true
    in_array($x, $list, 1);              // not a boolean literal
    in_array($x, $list, MY_CONST);       // not a literal true
    array_search($x, $list);             // strict missing
    array_keys($arr, $x);                // search value present, strict missing
    array_keys($arr, filter_value: $x);  // search value present (named), strict missing
    \in_array($x, $list);                // fully-qualified call, strict missing
    IN_ARRAY($x, $list);                 // uppercase name: strtolower() is load-bearing
    in_array(needle: $x, haystack: $list); // named needle/haystack, no strict arg

    // --- negative cases: must NOT be reported ---
    in_array($x, $list, true);           // strict true
    in_array($x, $list, strict: true);   // named strict true
    in_array($x, $list, TRUE);           // strict TRUE — case-insensitive boolean literal, OK
    array_search($x, $list, true);       // strict true
    array_keys($arr);                    // no search value, strict irrelevant
    array_keys($arr, $x, true);          // strict true
    $callable = in_array(...);           // first-class callable, not a real call
    in_array(...$args);                  // argument unpacking, positions unknown
    $haystack->in_array($x);             // method named in_array, not the function

    // --- early-return coverage paths ---
    strlen((string) $x);  // non-monitored function: name not in STRICT_REQUIRED_FROM, no error
    $fn = 'strlen';
    $fn($x);              // variable function name: name not instanceof Name, no error
}
