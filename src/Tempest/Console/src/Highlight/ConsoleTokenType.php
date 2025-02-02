<?php

declare(strict_types=1);

namespace Tempest\Console\Highlight;

use Tempest\Highlight\Tokens\TokenType;

enum ConsoleTokenType implements TokenType
{
    case EM;
    case H1;
    case H2;
    case STRONG;
    case UNDERLINE;

    public function getValue(): string
    {
        return $this->name;
    }

    public function canContain(TokenType $other): bool
    {
        return false;
    }
}
