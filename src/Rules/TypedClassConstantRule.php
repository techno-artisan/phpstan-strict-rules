<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use PHPStan\ShouldNotHappenException;

/**
 * @implements Rule<ClassConst>
 */
final class TypedClassConstantRule implements Rule
{
    public function getNodeType(): string
    {
        return ClassConst::class;
    }

    /**
     * @return list<IdentifierRuleError>
     * @throws ShouldNotHappenException
     */
    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->type !== null) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if ($classReflection === null) {
            throw new ShouldNotHappenException();
        }
        $className = $classReflection->getName();

        $errors = [];

        foreach ($node->consts as $const) {
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Class constant %s::%s must declare a native type.',
                $className,
                $const->name->toString(),
            ))
                ->identifier('technoArtisan.typedClassConstant')
                ->line($const->getStartLine())
                ->build();
        }

        return $errors;
    }
}
