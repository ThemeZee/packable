<?php

declare(strict_types=1);

namespace ThemeZee\Packable\Container;

use Psr\Container\ContainerInterface;

/**
 * @phpstan-import-type Service from \ThemeZee\Packable\Module\ServiceModule
 */
class ContainerConfigurator
{
    /** @var array<string, Service> */
    private array $services = [];
    private ?ContainerInterface $compiledContainer = null;
    /** @var ContainerInterface[] */
    private array $containers = [];

    /**
     * @param ContainerInterface[] $containers
     */
    public function __construct(array $containers = [])
    {
        array_map([$this, 'addContainer'], $containers);
    }

    /**
     * @param ContainerInterface $container
     * @return void
     */
    public function addContainer(ContainerInterface $container): void
    {
        $this->containers[] = $container;
    }

    /**
     * @param string $id
     * @param Service $service
     * @return void
     */
    public function addService(string $id, callable $service): void
    {
        /*
         * We are being intentionally permissive here,
         * allowing a simple workflow for *intentional* overrides
         * while accepting the (small?) risk of *accidental* overrides
         * that could be hard to notice and debug.
         */
        $this->services[$id] = $service;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function hasService(string $id): bool
    {
        if (array_key_exists($id, $this->services)) {
            return true;
        }

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return ContainerInterface
     *
     * @phpstan-assert ContainerInterface $this->compiledContainer
     */
    public function createReadOnlyContainer(): ContainerInterface
    {
        if ($this->compiledContainer === null) {
            $this->compiledContainer = new ReadOnlyContainer(
                $this->services,
                $this->containers
            );
        }

        return $this->compiledContainer;
    }
}
