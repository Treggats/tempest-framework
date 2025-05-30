<?php

declare(strict_types=1);

namespace Tempest\Database\QueryStatements;

use Tempest\Database\Config\DatabaseDialect;
use Tempest\Database\QueryStatement;

final readonly class BooleanStatement implements QueryStatement
{
    public function __construct(
        private string $name,
        private bool $nullable = false,
        private ?bool $default = null,
    ) {}

    public function compile(DatabaseDialect $dialect): string
    {
        $default = null;

        if ($this->default !== null) {
            $default = match ($dialect) {
                DatabaseDialect::POSTGRESQL => $this->default ? 'true' : 'false',
                default => $this->default ? '1' : '0',
            };
        }

        return sprintf(
            '`%s` BOOLEAN %s %s',
            $this->name,
            $default !== null ? "DEFAULT {$default}" : '',
            $this->nullable ? '' : 'NOT NULL',
        );
    }
}
