<?php

declare(strict_types=1);

namespace TechnoArtisan\PhpstanStrictRules\Tests\Rules\Data\TypedClassConstant;

final class Sample
{
    // positive: untyped constant must be flagged
    public const FOO = 1;

    // positive: multi-constant declaration → one error per constant, each on its own line
    public const
        BAR = 'a',
        BAZ = 'b';

    // negative: typed constant must NOT be flagged
    public const int TYPED = 2;

    // negative: typed multi-constant declaration
    public const string LEFT = 'l', RIGHT = 'r';
}

interface Contract
{
    // positive: untyped interface constant
    const QUX = 1;

    // negative: typed interface constant
    const int TYPED = 2;
}

enum Suit: string
{
    // enum cases are EnumCase nodes, not ClassConst — must NOT be flagged
    case Hearts = 'H';
    case Spades = 'S';

    // positive: untyped constant inside an enum
    const WILD = 1;

    // negative: typed constant inside an enum
    const int LIMIT = 4;
}
