<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Tests\Rules\Data;

function check(?string $value): bool
{
    return empty($value);
}
