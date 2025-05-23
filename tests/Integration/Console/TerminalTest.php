<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Console;

use Tempest\Console\Console;
use Tempest\Console\Terminal\Terminal;
use Tests\Tempest\Integration\FrameworkIntegrationTestCase;

/**
 * @internal
 */
final class TerminalTest extends FrameworkIntegrationTestCase
{
    public function test_supports_tty(): void
    {
        $this->console
            ->withoutPrompting()
            ->call(function (Console $console): void {
                $terminal = new Terminal($console);

                $terminal->disableTty();

                $this->assertFalse($terminal->supportsTty);
            });
    }
}
