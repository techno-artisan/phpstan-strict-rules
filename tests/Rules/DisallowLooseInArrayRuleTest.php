<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Tests\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use TechnoArtisan\PhpstanStrictRules\Rules\DisallowLooseInArrayRule;

/**
 * @extends RuleTestCase<DisallowLooseInArrayRule>
 */
final class DisallowLooseInArrayRuleTest extends RuleTestCase
{
    protected function getRule(): Rule
    {
        return new DisallowLooseInArrayRule();
    }

    public function testEveryLooseCallIsReportedAndNothingElse(): void
    {
        // analyse() asserts the COMPLETE error set: the eight loose calls are
        // flagged, and every negative case (strict: true, array_keys($arr), the
        // first-class callable, the ...$args unpacking, the Haystack::in_array()
        // method call) is implicitly asserted to stay clean.
        $this->analyse([__DIR__ . '/data/loose-in-array.php'], [
            [self::message('in_array'), 29],
            [self::message('in_array'), 30],
            [self::message('in_array'), 31],
            [self::message('in_array'), 32],
            [self::message('in_array'), 33],
            [self::message('array_search'), 34],
            [self::message('array_keys'), 35],
            [self::message('array_keys'), 36],
        ]);
    }

    public function testErrorsCarryTheConventionalIdentifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/data/loose-in-array.php']);

        self::assertCount(8, $errors);
        foreach ($errors as $error) {
            self::assertSame('technoArtisan.looseInArray', $error->getIdentifier());
        }
    }

    private static function message(string $function): string
    {
        return sprintf(
            'Call to %s() must pass true as the $strict argument; loose comparison coerces types and hides bugs.',
            $function,
        );
    }
}
