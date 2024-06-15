<?php

declare(strict_types=1);

namespace Tempest\View;

interface View
{
    public function path(string $path): self;

    public function getPath(): string;

    public function getData(): array;

    public function getRawData(): array;

    public function getRaw(string $key): mixed;

    public function get(string $key): mixed;

    public function data(...$params): self;

    public function raw(string $name): ?string;

    public function slot(string $name = 'slot'): ?string;

    /**
     * @param string $name
     * @return \Tempest\Validation\Rule[]
     */
    public function getErrorsFor(string $name): array;

    public function hasErrors(): bool;

    public function original(string $name, mixed $default = ''): mixed;
}
