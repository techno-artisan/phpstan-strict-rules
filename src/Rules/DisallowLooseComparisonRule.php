<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BinaryOp\Equal;
use PhpParser\Node\Expr\BinaryOp\NotEqual;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Disallows the loose comparison operators == and != (the latter also written
 * <>, which PHP parses to the same NotEqual node); use the strict operators
 * === / !== instead. Loose comparison coerces operand types and hides bugs
 * (e.g. 0 == 'foo', '1e1' == '10' and null == false are all true).
 *
 * The rule is intentionally syntactic — it inspects the operator AST, not the
 * inferred operand types — so every loose comparison is reported regardless of
 * type, mirroring DisallowEmptyConstructRule and DisallowLooseInArrayRule. This
 * is deliberately stricter than phpstan/phpstan-strict-rules, which flags only
 * provably type-unsafe comparisons.
 *
 * @implements Rule<BinaryOp>
 */
final class DisallowLooseComparisonRule implements Rule
{
    public function getNodeType(): string
    {
        return BinaryOp::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof Equal && !$node instanceof NotEqual) {
            return [];
        }

        [$loose, $strict] = $node instanceof Equal ? ['==', '==='] : ['!=', '!=='];

        return [
            RuleErrorBuilder::message(sprintf(
                'Loose comparison with %s is not allowed; use %s instead. It coerces operand types and hides bugs.',
                $loose,
                $strict,
            ))
                ->identifier('technoArtisan.looseComparison')
                ->build(),
        ];
    }
}
