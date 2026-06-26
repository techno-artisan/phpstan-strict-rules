<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Tests;

use PHPStan\Rules\Rule;
use PHPStan\Testing\PHPStanTestCase;
use ReflectionClass;

/**
 * Self-discipline guard. Instead of listing every rule by hand, this test
 * discovers each rule class in src/Rules/ and enforces — for all of them at
 * once — the invariants the package relies on:
 *
 *  1. it is registered in rules.neon (the single entry point consumers include);
 *  2. it is final;
 *  3. its file declares strict_types=1.
 *
 * Invariant 1 is the dangerous one: a rule that is added but never wired into
 * rules.neon would run silently never at consumers. RuleTestCase cannot catch
 * that, and a hand-maintained list forgets. This test fails the build instead.
 */
final class RulesRegistrationTest extends PHPStanTestCase
{
    private const string RULES_DIR = __DIR__ . '/../src/Rules';

    private const string RULES_NAMESPACE = 'TechnoArtisan\PhpstanStrictRules\Rules\\';

    /**
     * @return list<string>
     */
    public static function getAdditionalConfigFiles(): array
    {
        return [__DIR__ . '/../rules.neon'];
    }

    public function testEveryRuleClassIsRegisteredViaRulesNeon(): void
    {
        $registered = array_map(
            static fn (Rule $rule): string => $rule::class,
            self::getContainer()->getServicesByTag('phpstan.rules.rule'),
        );

        foreach (self::ruleClasses() as $ruleClass) {
            self::assertContains(
                $ruleClass,
                $registered,
                sprintf('%s is not registered in rules.neon and would run silently never at consumers.', $ruleClass),
            );
        }
    }

    public function testEveryRuleClassIsFinal(): void
    {
        foreach (self::ruleClasses() as $ruleClass) {
            self::assertTrue(
                (new ReflectionClass($ruleClass))->isFinal(),
                sprintf('%s must be final.', $ruleClass),
            );
        }
    }

    public function testEveryRuleFileDeclaresStrictTypes(): void
    {
        foreach (self::ruleClasses() as $ruleClass) {
            $file = (new ReflectionClass($ruleClass))->getFileName();
            self::assertIsString($file);
            self::assertStringContainsString(
                'declare(strict_types=1);',
                (string) file_get_contents($file),
                sprintf('%s must declare(strict_types=1).', $ruleClass),
            );
        }
    }

    /**
     * Discovers every concrete Rule class shipped in src/Rules/. Files that are
     * not concrete rules (e.g. a future abstract base class) are skipped, so the
     * guard never chokes as the package grows.
     *
     * @return list<class-string<Rule>>
     */
    private static function ruleClasses(): array
    {
        $classes = [];

        foreach (scandir(self::RULES_DIR) ?: [] as $entry) {
            if (!str_ends_with($entry, '.php')) {
                continue;
            }

            $class = self::RULES_NAMESPACE . substr($entry, 0, -4);
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new ReflectionClass($class);
            if ($reflection->isAbstract() || !$reflection->implementsInterface(Rule::class)) {
                continue;
            }

            $classes[] = $class;
        }

        self::assertNotEmpty($classes, 'No rule classes were discovered in src/Rules/.');

        return $classes;
    }
}
