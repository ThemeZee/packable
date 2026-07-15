<?php
/**
 * Configures and compiles the read-only container.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable\Container;

use Psr\Container\ContainerInterface;

/**
 * Collects services and child containers and builds the compiled container.
 */
class ContainerConfigurator {

	/**
	 * Registered service factories, keyed by id.
	 *
	 * @var array<string, callable(ContainerInterface): mixed>
	 */
	private array $services = array();

	/**
	 * Compiled container, created lazily.
	 *
	 * @var ContainerInterface|null
	 */
	private ?ContainerInterface $compiledContainer = null;

	/**
	 * Child containers to delegate lookups to.
	 *
	 * @var ContainerInterface[]
	 */
	private array $containers = array();

	/**
	 * Constructor.
	 *
	 * @param ContainerInterface[] $containers Child containers to delegate to.
	 */
	public function __construct( array $containers = array() ) {
		array_map( array( $this, 'addContainer' ), $containers );
	}

	/**
	 * Adds a child container to delegate lookups to.
	 *
	 * @param ContainerInterface $container Container to add.
	 * @return void
	 */
	public function addContainer( ContainerInterface $container ): void {
		$this->containers[] = $container;
	}

	/**
	 * Registers a service factory under the given id.
	 *
	 * @param string                              $id      Service id.
	 * @param callable(ContainerInterface): mixed $service Factory callback.
	 * @return void
	 */
	public function addService( string $id, callable $service ): void {
		/*
		 * We are being intentionally permissive here,
		 * allowing a simple workflow for *intentional* overrides
		 * while accepting the (small?) risk of *accidental* overrides
		 * that could be hard to notice and debug.
		 */
		$this->services[ $id ] = $service;
	}

	/**
	 * Checks whether a service with the given id is available.
	 *
	 * @param string $id Service id.
	 * @return bool
	 */
	public function hasService( string $id ): bool {
		if ( array_key_exists( $id, $this->services ) ) {
			return true;
		}

		foreach ( $this->containers as $container ) {
			if ( $container->has( $id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Builds (once) and returns the compiled read-only container.
	 *
	 * @return ContainerInterface
	 */
	public function createReadOnlyContainer(): ContainerInterface {
		if ( null === $this->compiledContainer ) {
			$this->compiledContainer = new ReadOnlyContainer(
				$this->services,
				$this->containers
			);
		}

		return $this->compiledContainer;
	}
}
