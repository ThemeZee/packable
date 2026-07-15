<?php
/**
 * Read-only PSR-11 container compiled from registered services and child containers.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable\Container;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Resolves services once and delegates unknown ids to child containers.
 */
class ReadOnlyContainer implements ContainerInterface {

	/**
	 * Service factories that have not been resolved yet, keyed by id.
	 *
	 * @var array<string, callable(ContainerInterface): mixed>
	 */
	private array $services;

	/**
	 * Child containers to delegate lookups to.
	 *
	 * @var ContainerInterface[]
	 */
	private array $containers;

	/**
	 * Already resolved services, keyed by id.
	 *
	 * @var array<string, mixed>
	 */
	private array $resolvedServices = array();

	/**
	 * Constructor.
	 *
	 * @param array<string, callable(ContainerInterface): mixed> $services   Service factories keyed by id.
	 * @param ContainerInterface[]                               $containers Child containers to delegate to.
	 */
	public function __construct(
		array $services,
		array $containers
	) {

		$this->services   = $services;
		$this->containers = $containers;
	}

	/**
	 * Resolves and returns the service registered under the given id.
	 *
	 * @param string $id Service id.
	 * @return mixed
	 * @throws NotFoundExceptionInterface When no service with the given id exists.
	 */
	public function get( string $id ) {
		if ( array_key_exists( $id, $this->resolvedServices ) ) {
			return $this->resolvedServices[ $id ];
		}

		if ( array_key_exists( $id, $this->services ) ) {
			$service                       = $this->services[ $id ]( $this );
			$this->resolvedServices[ $id ] = $service;
			unset( $this->services[ $id ] );

			return $service;
		}

		foreach ( $this->containers as $container ) {
			if ( $container->has( $id ) ) {
				return $container->get( $id );
			}
		}

		$error     = "Service with ID {$id} not found.";
		$exception = new class(esc_html( $error )) extends \Exception implements NotFoundExceptionInterface
		{
		};

		throw $exception;
	}

	/**
	 * Checks whether a service with the given id is available.
	 *
	 * @param string $id Service id.
	 * @return bool
	 */
	public function has( string $id ): bool {
		if ( array_key_exists( $id, $this->services ) ) {
			return true;
		}

		if ( array_key_exists( $id, $this->resolvedServices ) ) {
			return true;
		}

		foreach ( $this->containers as $container ) {
			if ( $container->has( $id ) ) {
				return true;
			}
		}

		return false;
	}
}
