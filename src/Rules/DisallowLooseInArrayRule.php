<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Disallows loose comparison in in_array(), array_search() and array_keys():
 * each must pass true as the $strict argument. Loose comparison silently coerces
 * types and hides bugs (e.g. in_array('1', [1, 2, 3]) is true).
 *
 * The rule is intentionally syntactic — it inspects the call AST, not inferred
 * types — so every loose call is reported regardless of the operand types,
 * mirroring DisallowEmptyConstructRule.
 *
 * @implements Rule<FuncCall>
 */
final class DisallowLooseInArrayRule implements Rule
{
    /**
     * Function name (lower-case) => the leading argument index whose presence
     * makes $strict mandatory. in_array/array_search demand it unconditionally
     * (the needle at index 0 is always present); array_keys only when its
     * search value (filter_value at index 1) is present.
     */
    private const array STRICT_REQUIRED_FROM = [
        'in_array' => 0,
        'array_search' => 0,
        'array_keys' => 1,
    ];

    /** The positional index of the $strict argument across all three functions. */
    private const int STRICT_ARG_INDEX = 2;

    /** The named-argument form of array_keys()'s search value. */
    private const string SEARCH_VALUE_ARG_NAME = 'filter_value';

    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->name instanceof Name) {
            return [];
        }

        $functionName = strtolower($node->name->toString());
        if (!array_key_exists($functionName, self::STRICT_REQUIRED_FROM)) {
            return [];
        }

        // First-class callable in_array(...) — no real call happens.
        if ($node->isFirstClassCallable()) {
            return [];
        }

        /** @var list<Arg> $args */
        $args = array_values($node->getArgs());

        // Argument unpacking (...$args) — positional layout cannot be trusted.
        foreach ($args as $arg) {
            if ($arg->unpack) {
                return [];
            }
        }

        if (!$this->searchValueIsPresent($functionName, $args)) {
            return [];
        }

        if ($this->strictArgumentIsTrue($args)) {
            return [];
        }

        return [
            RuleErrorBuilder::message(sprintf(
                'Call to %s() must pass true as the $strict argument; loose comparison coerces types and hides bugs.',
                $functionName,
            ))
                ->identifier('technoArtisan.looseInArray')
                ->build(),
        ];
    }

    /**
     * @param list<Arg> $args
     */
    private function searchValueIsPresent(string $functionName, array $args): bool
    {
        $requiredFrom = self::STRICT_REQUIRED_FROM[$functionName];

        // in_array / array_search: $strict is demanded unconditionally.
        if ($requiredFrom === 0) {
            return true;
        }

        // array_keys: $strict is demanded only when a search value is present,
        // either positionally at index 1 or as the named filter_value argument.
        foreach ($args as $index => $arg) {
            if ($arg->name === null) {
                if ($index >= $requiredFrom) {
                    return true;
                }
            } elseif ($arg->name->toString() === self::SEARCH_VALUE_ARG_NAME) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Arg> $args
     */
    private function strictArgumentIsTrue(array $args): bool
    {
        foreach ($args as $index => $arg) {
            $isStrictArg = $arg->name === null
                ? $index === self::STRICT_ARG_INDEX
                : $arg->name->toString() === 'strict';

            if ($isStrictArg) {
                return $this->isBooleanTrue($arg->value);
            }
        }

        return false;
    }

    private function isBooleanTrue(Expr $value): bool
    {
        return $value instanceof ConstFetch
            && strtolower($value->name->toString()) === 'true';
    }
}
