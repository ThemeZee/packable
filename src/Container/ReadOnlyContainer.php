<?php

declare(strict_types=1);

namespace ThemeZee\Packable\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * @phpstan-import-type Service from \ThemeZee\Packable\Module\ServiceModule
 */
class ReadOnlyContainer implements ContainerInterface
{
    /** @var array<string, Service> */
    private array $services;
    /** @var array<string, bool> */
    private array $factoryIds;
    /** @var ContainerInterface[] */
    private array $containers;
    /** @var array<string, mixed> */
    private array $resolvedServices = [];

    /**
     * @param array<string, Service> $services
     * @param array<string, bool> $factoryIds
     * @param ContainerInterface[] $containers
     */
    public function __construct(
        array $services,
        array $factoryIds,
        array $containers
    ) {

        $this->services = $services;
        $this->factoryIds = $factoryIds;
        $this->containers = $containers;
    }

    /**
     * @param string $id
     * @return mixed
     */
    public function get(string $id)
    {
        if (array_key_exists($id, $this->resolvedServices)) {
            return $this->resolvedServices[$id];
        }

        if (array_key_exists($id, $this->services)) {
            $service = $this->services[$id]($this);

            if (!isset($this->factoryIds[$id])) {
                $this->resolvedServices[$id] = $service;
                unset($this->services[$id]);
            }

            return $service;
        }

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                return $container->get($id);
            }
        }

        $error = "Service with ID {$id} not found.";
        throw new class (esc_html($error)) extends \Exception implements NotFoundExceptionInterface
        {
        };
    }

    /**
     * @param string $id
     * @return bool
     */
    public function has(string $id): bool
    {
        if (array_key_exists($id, $this->services)) {
            return true;
        }

        if (array_key_exists($id, $this->resolvedServices)) {
            return true;
        }

        foreach ($this->containers as $container) {
            if ($container->has($id)) {
                return true;
            }
        }

        return false;
    }
}
