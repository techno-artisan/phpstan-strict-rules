<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Tests\Rules;

use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use TechnoArtisan\PhpstanStrictRules\Rules\TypedClassConstantRule;

/**
 * @extends RuleTestCase<TypedClassConstantRule>
 */
final class TypedClassConstantRuleTest extends RuleTestCase
{
    private const string NS = 'TechnoArtisan\\PhpstanStrictRules\\Tests\\Rules\\Data\\TypedClassConstant';

    protected function getRule(): Rule
    {
        return new TypedClassConstantRule();
    }

    public function testUntypedConstantsAreReportedWithClassAndConstantNameAndNothingElse(): void
    {
        // analyse() asserts the COMPLETE error set, so the typed constants, the
        // typed multi-declaration and the enum cases are implicitly asserted clean.
        $this->analyse([__DIR__ . '/data/typed-class-constant.php'], [
            [$this->message('Sample', 'FOO'), 10],
            [$this->message('Sample', 'BAR'), 14],
            [$this->message('Sample', 'BAZ'), 15],
            [$this->message('Contract', 'QUX'), 27],
            [$this->message('Suit', 'WILD'), 40],
        ]);
    }

    public function testErrorsCarryTheConventionalIdentifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/data/typed-class-constant.php']);

        self::assertCount(5, $errors);
        foreach ($errors as $error) {
            self::assertSame('technoArtisan.typedClassConstant', $error->getIdentifier());
        }
    }

    private function message(string $class, string $constant): string
    {
        return sprintf('Class constant %s\\%s::%s must declare a native type.', self::NS, $class, $constant);
    }
}
