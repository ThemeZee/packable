<?php
/**
 * Base test case with shared stubs and helpers.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable\Tests;

use Brain\Monkey;
use ThemeZee\Packable\Module\ExecutableModule;
use ThemeZee\Packable\Module\Module;
use ThemeZee\Packable\Module\ServiceModule;
use ThemeZee\Packable\Package;
use ThemeZee\Packable\Properties\Properties;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase as FrameworkTestCase;
use Psr\Container\ContainerInterface;

/**
 * Shared base test case for the package test suite.
 */
abstract class TestCase extends FrameworkTestCase {

	use MockeryPHPUnitIntegration;

	/**
	 * Sets up Brain Monkey before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Monkey\Functions\stubEscapeFunctions();
	}

	/**
	 * Tears down Brain Monkey after each test.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Returns a stubbed Properties instance.
	 *
	 * @param string $basename Base name to return.
	 * @param bool   $isDebug  Debug flag to return.
	 *
	 * @return Properties|MockInterface
	 */
	protected function stubProperties(
		string $basename = 'basename',
		bool $isDebug = false
	): Properties {

		$stub = \Mockery::mock( Properties::class );
		$stub->allows( 'basename' )->andReturn( $basename );
		$stub->allows( 'isDebug' )->andReturn( $isDebug );

		return $stub;
	}

	/**
	 * Returns a stubbed module implementing the given interfaces.
	 *
	 * @param string       $id            Module id to return.
	 * @param class-string ...$interfaces Extra interfaces the module should implement.
	 *
	 * @return Module|MockInterface
	 */
	protected function stubModule( string $id = 'module', string ...$interfaces ) {
		$stub = \Mockery::mock( Module::class, ...$interfaces );
		$stub->allows( 'id' )->andReturn( $id );

		if ( in_array( ServiceModule::class, $interfaces, true ) ) {
			$stub->allows( 'services' )->byDefault()->andReturn( array() );
		}

		if ( in_array( ExecutableModule::class, $interfaces, true ) ) {
			$stub->allows( 'run' )->byDefault()->andReturn( false );
		}

		return $stub;
	}

	/**
	 * Returns a package with a single service module already added.
	 *
	 * @param string $suffix Suffix used for module, service and package names.
	 * @param bool   $debug  Debug flag for the package properties.
	 *
	 * @return Package
	 */
	protected function stubSimplePackage( string $suffix, bool $debug = false ): Package {
		$module = $this->stubModule( "module_{$suffix}", ServiceModule::class );
		$module->expects( 'services' )->andReturn( $this->stubServices( "service_{$suffix}" ) );
		$properties = $this->stubProperties( "package_{$suffix}", $debug );

		return Package::new( $properties )->addModule( $module );
	}

	/**
	 * Returns a map of service factories for the given ids.
	 *
	 * @param string ...$ids Service ids to create factories for.
	 *
	 * @return array<string, callable>
	 */
	protected function stubServices( string ...$ids ): array {
		$services = array();
		foreach ( $ids as $id ) {
			$services[ $id ] = static function () use ( $id ): \ArrayObject {
				return new \ArrayObject( array( 'id' => $id ) );
			};
		}

		return $services;
	}

	/**
	 * Returns a simple PSR-11 container backed by service factories.
	 *
	 * @param string ...$ids Service ids the container should provide.
	 *
	 * @return ContainerInterface
	 */
	protected function stubContainer( string ...$ids ): ContainerInterface {
		return new class($this->stubServices( ...$ids )) implements ContainerInterface {
			/**
			 * Service factories, keyed by id.
			 *
			 * @var array<string, callable>
			 */
			private array $services;

			/**
			 * Constructor.
			 *
			 * @param array<string, callable> $services Service factories keyed by id.
			 */
			public function __construct( array $services ) {
				$this->services = $services;
			}

			/**
			 * Resolves and returns the service for the given id.
			 *
			 * @param string $id Service id.
			 * @return mixed
			 * @throws \Exception When the service is not found.
			 */
			public function get( string $id ) {
				if ( ! isset( $this->services[ $id ] ) ) {
					throw new \Exception( "Service {$id} not found." );
				}

				return $this->services[ $id ]( $this );
			}

			/**
			 * Returns whether a service with the given id exists.
			 *
			 * @param string $id Service id.
			 * @return bool
			 */
			public function has( string $id ): bool {
				return isset( $this->services[ $id ] );
			}
		};
	}

	/**
	 * Asserts a throwable's message matches the given pattern.
	 *
	 * @param \Throwable $throwable Throwable to inspect.
	 * @param string     $pattern   Regex pattern (without delimiters).
	 *
	 * @return void
	 */
	protected function assertThrowableMessageMatches( \Throwable $throwable, string $pattern ): void {
		static::assertSame( 1, preg_match( "/{$pattern}/i", $throwable->getMessage() ) );
	}
}
