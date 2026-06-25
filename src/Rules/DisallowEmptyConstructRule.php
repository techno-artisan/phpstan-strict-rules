<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\Empty_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Disallows the empty() language construct because its loose semantics hide bugs
 * (e.g. "0", 0.0, "0" and [] are all "empty"). Use an explicit strict check instead.
 *
 * @implements Rule<Empty_>
 */
final class DisallowEmptyConstructRule implements Rule
{
    public function getNodeType(): string
    {
        return Empty_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return [
            RuleErrorBuilder::message('Construct empty() is not allowed. Use an explicit strict comparison instead.')
                ->identifier('technoArtisan.disallowedEmpty')
                ->build(),
        ];
    }
}
