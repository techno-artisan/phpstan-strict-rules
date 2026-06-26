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
        // analyse() asserts the COMPLETE error set: the eleven loose calls are
        // flagged, and every negative case (strict: true, array_keys($arr), the
        // first-class callable, the ...$args unpacking, the $haystack->in_array()
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
            [self::message('in_array'), 37],
            [self::message('in_array'), 38],
            [self::message('in_array'), 39],
        ]);
    }

    public function testErrorsCarryTheConventionalIdentifier(): void
    {
        $errors = $this->gatherAnalyserErrors([__DIR__ . '/data/loose-in-array.php']);

        self::assertCount(11, $errors);
        foreach ($errors as $error) {
            self::assertSame('technoArtisan.looseInArray', $error->getIdentifier());
        }
    }

    public function testStrictArgIsNotMisidentifiedWhenArgListHasNonSequentialKeys(): void
    {
        // array_values() in processNode() re-sequences the Arg list so that integer
        // keys equal actual positional indices. Without it, a non-sequential Arg array
        // (index 0 and 2, gap at 1) would cause strictArgumentIsTrue() to falsely
        // match the arg at key 2 as STRICT_ARG_INDEX, producing a false negative.
        // This test constructs such a node directly to keep the guard exercised.
        $strictArg = new \PhpParser\Node\Arg(
            new \PhpParser\Node\Expr\ConstFetch(new \PhpParser\Node\Name('true')),
        );

        // FuncCall with args at indices 0 and 2 (key 1 missing).
        $call = new \PhpParser\Node\Expr\FuncCall(
            new \PhpParser\Node\Name('in_array'),
            [
                0 => new \PhpParser\Node\Arg(new \PhpParser\Node\Expr\Variable('x')),
                2 => $strictArg,
            ],
        );

        /** @var \PHPStan\Analyser\Scope $scope */
        $scope = $this->createStub(\PHPStan\Analyser\Scope::class);

        // array_values() renumbers [0, 2] → [0, 1], so the strict arg lands at
        // index 1, not 2. STRICT_ARG_INDEX is 2, so strictArgumentIsTrue() returns
        // false and the rule correctly reports a loose call (one error).
        $errors = $this->getRule()->processNode($call, $scope);
        self::assertCount(1, $errors);
    }

    public function testFirstClassCallableIsNotReported(): void
    {
        // PHPStan 2.x does not dispatch FuncCall rules for first-class callables at
        // the framework level, so the isFirstClassCallable() guard in processNode()
        // is never reached via analyse().  This test constructs the node directly
        // so the branch is exercised: a FCC must produce zero errors.
        $fcc = new \PhpParser\Node\Expr\FuncCall(
            new \PhpParser\Node\Name('in_array'),
            [new \PhpParser\Node\VariadicPlaceholder()],
        );

        /** @var \PHPStan\Analyser\Scope $scope */
        $scope = $this->createStub(\PHPStan\Analyser\Scope::class);

        self::assertSame([], $this->getRule()->processNode($fcc, $scope));
    }

    private static function message(string $function): string
    {
        return sprintf(
            'Call to %s() must pass true as the $strict argument; loose comparison coerces types and hides bugs.',
            $function,
        );
    }
}
