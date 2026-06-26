<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Tests\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use TechnoArtisan\PhpstanStrictRules\Rules\DisallowLooseComparisonRule;

/**
 * @extends RuleTestCase<DisallowLooseComparisonRule>
 */
final class DisallowLooseComparisonRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new DisallowLooseComparisonRule();
    }

    public function testEveryLooseComparisonIsReportedAndNothingElse(): void
    {
        // analyse() asserts the COMPLETE error set: the seven loose comparisons
        // are flagged (including == null and the <> alias of !=), and every
        // negative case (===, !==, <, >, <=, >=, <=>) is implicitly asserted to
        // stay clean.
        $this->analyse([__DIR__ . '/data/loose-comparison.php'], [
            [self::message('==', '==='), 13],
            [self::message('!=', '!=='), 14],
            [self::message('!=', '!=='), 15],
            [self::message('==', '==='), 16],
            [self::message('==', '==='), 17],
            [self::message('==', '==='), 18],
            [self::message('!=', '!=='), 21],
        ]);
    }

    public function testErrorsCarryTheConventionalIdentifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/data/loose-comparison.php']);

        self::assertCount(7, $errors);
        foreach ($errors as $error) {
            self::assertSame('technoArtisan.looseComparison', $error->getIdentifier());
        }
    }

    private static function message(string $loose, string $strict): string
    {
        return sprintf(
            'Loose comparison with %s is not allowed; use %s instead. It coerces operand types and hides bugs.',
            $loose,
            $strict,
        );
    }
}
