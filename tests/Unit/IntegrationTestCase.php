<?php

declare(strict_types=1);

namespace Tests\Tempest\Unit;

use PHPUnit\Framework\TestCase;
use Tempest\Container\Container;
use Tempest\Core\AppConfig;
use Tempest\Core\DiscoveryLocation;
use Tempest\Core\Kernel;

abstract class IntegrationTestCase extends TestCase
{
    protected AppConfig $appConfig;

    protected Kernel $kernel;

    protected Container $container;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appConfig = new AppConfig(
            root: getcwd(),
            discoveryCache: true,
            discoveryLocations: [
                new DiscoveryLocation(
                    'Tests\\Tempest\\Fixtures\\',
                    __DIR__ . '/../Fixtures/',
                ),
            ],
        );

        $this->kernel = new Kernel($this->appConfig);
        $this->container = $this->kernel->init();
    }
}
