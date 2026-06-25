<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\PHPStanTestCase;
use TechnoArtisan\PhpstanStrictRules\Rules\DisallowEmptyConstructRule;
use TechnoArtisan\PhpstanStrictRules\Rules\TypedClassConstantRule;

/**
 * Guards the wiring that RuleTestCase cannot: that rules.neon — the single entry
 * point consumers include — actually registers every rule under the rules tag.
 */
final class RulesRegistrationTest extends PHPStanTestCase
{
    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../rules.neon'];
    }

    public function testEveryRuleIsRegisteredViaRulesNeon(): void
    {
        $registered = array_map(
            static fn (Rule $rule): string => $rule::class,
            self::getContainer()->getServicesByTag('phpstan.rules.rule'),
        );

        self::assertContains(DisallowEmptyConstructRule::class, $registered);
        self::assertContains(TypedClassConstantRule::class, $registered);
    }
}
