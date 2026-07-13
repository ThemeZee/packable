<?php

declare(strict_types=1);

namespace ThemeZee\Packable\Tests\Unit\Container;

use Brain\Monkey;
use ThemeZee\Packable\Container\PackageProxyContainer;
use ThemeZee\Packable\Package;
use ThemeZee\Packable\Tests\TestCase;

class PackageProxyContainerTest extends TestCase
{
    /**
     * @test
     */
    public function testAccessingContainerEarlyThrows(): void
    {
        $package = Package::new($this->stubProperties());

        $container = new PackageProxyContainer($package);
        static::assertFalse($container->has('test'));

        $this->expectExceptionMessageMatches('/is not ready yet/i');
        $container->get('test');
    }

    /**
     * @test
     */
    public function testAccessingFailedPackageEarlyThrows(): void
    {
        $package = Package::new($this->stubProperties());

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->andThrow(new \Error());

        $container = new PackageProxyContainer($package->build());
        static::assertFalse($container->has('test'));

        $this->expectExceptionMessageMatches('/is errored/i');
        $container->get('test');
    }
}
