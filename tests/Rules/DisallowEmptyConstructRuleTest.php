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
    private const string MESSAGE = 'Construct empty() is not allowed. Use an explicit strict comparison instead.';

    protected function getRule(): Rule
    {
        return new DisallowEmptyConstructRule();
    }

    public function testEveryEmptyConstructIsReportedAndNothingElse(): void
    {
        // analyse() asserts the COMPLETE error set: the four empty() language
        // constructs are flagged, and every other line (isset, ===, !, the
        // Box::empty() method call, ...) is implicitly asserted to be clean.
        $this->analyse([__DIR__ . '/data/empty-construct.php'], [
            [self::MESSAGE, 30],
            [self::MESSAGE, 31],
            [self::MESSAGE, 32],
            [self::MESSAGE, 35],
        ]);
    }

    public function testErrorsCarryTheConventionalIdentifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/data/empty-construct.php']);

        self::assertCount(4, $errors);
        foreach ($errors as $error) {
            self::assertSame('technoArtisan.disallowedEmpty', $error->getIdentifier());
        }
    }
}
