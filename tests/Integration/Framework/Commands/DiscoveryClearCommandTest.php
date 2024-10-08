<?php

declare(strict_types=1);

namespace Tests\Tempest\Integration\Framework\Commands;

use Tempest\Core\AppConfig;
use Tests\Tempest\Integration\Console\Commands\MyDiscovery;
use Tests\Tempest\Integration\FrameworkIntegrationTestCase;

/**
 * @internal
 * @small
 */
class DiscoveryClearCommandTest extends FrameworkIntegrationTestCase
{
    public function test_it_clears_discovery_cache(): void
    {
        $appConfig = $this->container->get(AppConfig::class);

        MyDiscovery::$cacheCleared = false;

        $appConfig->discoveryClasses = [MyDiscovery::class];

        $this->console->call('discovery:clear');

        $this->assertTrue(MyDiscovery::$cacheCleared);
    }
}
