<?php

declare(strict_types=1);

namespace ThemeZee\Packable\Module;

use Psr\Container\ContainerInterface;

interface ServiceModule extends Module
{
    /**
     * Return application services.
     *
     * Array keys will be services' IDs in the container, array values are callback that
     * accepts a PSR-11 container as parameter and return an instance of the service.
     * Services are "cached", so the given callback is called once the first time `get()` is called
     * in the container, and on subsequent `get()` the same instance is returned again and again.
     *
     * @return array<string, callable(ContainerInterface): mixed>
     */
    public function services(): array;
}
