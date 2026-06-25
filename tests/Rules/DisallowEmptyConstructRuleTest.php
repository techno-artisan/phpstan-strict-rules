<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Tests\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use TechnoArtisan\PhpstanStrictRules\Rules\DisallowEmptyConstructRule;

/**
 * @extends RuleTestCase<DisallowEmptyConstructRule>
 */
final class DisallowEmptyConstructRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new DisallowEmptyConstructRule();
    }

    public function testEmptyConstructIsReported(): void
    {
        $this->analyse([__DIR__ . '/data/empty-construct.php'], [
            [
                'Construct empty() is not allowed. Use an explicit strict comparison instead.',
                9,
            ],
        ]);
    }
}
