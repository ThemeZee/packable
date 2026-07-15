<?php
/**
 * Tests for ReadOnlyContainer.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable\Tests\Unit\Container;

use ThemeZee\Packable\Container\ReadOnlyContainer as Container;
use ThemeZee\Packable\Tests\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Tests service resolution and delegation of the read-only container.
 */
class ReadOnlyContainerTest extends TestCase {

	/**
	 * Tests the empty container basics.
	 *
	 * @test
	 */
	public function testBasic(): void {
		$testee = $this->factoryContainer();

		static::assertInstanceOf( ContainerInterface::class, $testee );
		static::assertFalse( $testee->has( 'unknown' ) );
	}

	/**
	 * Tests that getting an unknown service throws.
	 *
	 * @test
	 */
	public function testGetUnknown(): void {
		static::expectException( NotFoundExceptionInterface::class );

		$testee = $this->factoryContainer();
		$testee->get( 'unknown' );
	}

	/**
	 * Tests has() and get() for a registered service.
	 *
	 * @test
	 * @dataProvider provideServices
	 *
	 * @param mixed    $expected Expected resolved value.
	 * @param callable $service  Service factory.
	 */
	public function testHasGetService( $expected, callable $service ): void {
		$expectedId = 'service';
		$services   = array( $expectedId => $service );
		$testee     = $this->factoryContainer( $services );

		// check in Services.
		static::assertTrue( $testee->has( $expectedId ) );
		// resolve Service.
		static::assertSame( $expected, $testee->get( $expectedId ) );
		// check in resolved Services.
		static::assertTrue( $testee->has( $expectedId ) );
	}

	/**
	 * Provides services of different value types.
	 *
	 * @return \Generator
	 */
	public static function provideServices(): \Generator {
		$service = new \stdClass();
		yield 'object service' => array(
			$service,
			static function () use ( $service ): object {
				return $service;
			},
		);

		$service = 'foo';
		yield 'string service' => array(
			$service,
			static function () use ( $service ): string {
				return $service;
			},
		);

		$service = array( 'foo', 'bar' );
		yield 'array service' => array(
			$service,
			static function () use ( $service ): array {
				return $service;
			},
		);
	}

	/**
	 * Tests resolving a service from a child container.
	 *
	 * @test
	 */
	public function testHasGetServiceFromChildContainer(): void {
		$expectedKey   = 'service';
		$expectedValue = new \stdClass();

		$childContainer = new class($expectedKey, $expectedValue) implements ContainerInterface {
			/**
			 * Values keyed by id.
			 *
			 * @var array<string, \stdClass>
			 */
			private array $data = array();

			/**
			 * Constructor.
			 *
			 * @param string    $key   Value id.
			 * @param \stdClass $value Value to store.
			 */
			public function __construct( string $key, \stdClass $value ) {
				$this->data[ $key ] = $value;
			}

			/**
			 * Returns the value for the given id.
			 *
			 * @param string $id Value id.
			 * @return mixed
			 */
			public function get( string $id ) {
				return $this->data[ $id ];
			}

			/**
			 * Returns whether a value with the given id exists.
			 *
			 * @param string $id Value id.
			 * @return bool
			 */
			public function has( string $id ): bool {
				return isset( $this->data[ $id ] );
			}
		};

		$testee = $this->factoryContainer( array(), array( $childContainer ) );

		// check in child Container.
		static::assertTrue( $testee->has( $expectedKey ) );
		// resolve Service.
		static::assertSame( $expectedValue, $testee->get( $expectedKey ) );
		// check in resolved Services.
		static::assertTrue( $testee->has( $expectedKey ) );
	}

	/**
	 * Tests that resolved services are cached and reused.
	 *
	 * @test
	 */
	public function testServicesAreCached(): void {
		$expectedServiceKey = 'service';
		$services           = array(
			$expectedServiceKey => function (): object {
				return new class() {
					/**
					 * Number of times count() was called.
					 *
					 * @var int
					 */
					protected int $serviceCounter = 0;

					/**
					 * Increments and returns the call counter.
					 *
					 * @return int
					 */
					public function count(): int {
						++$this->serviceCounter;

						return $this->serviceCounter;
					}
				};
			},
		);

		$testee = $this->factoryContainer( $services );

		// Services are cached and same instance is returned.
		static::assertSame( 1, $testee->get( $expectedServiceKey )->count() );
		static::assertSame( 2, $testee->get( $expectedServiceKey )->count() );
	}

	/**
	 * Builds a read-only container for testing.
	 *
	 * @param array<string, callable(ContainerInterface): mixed> $services   Service factories keyed by id.
	 * @param ContainerInterface[]                               $containers Child containers to delegate to.
	 *
	 * @return Container
	 */
	private function factoryContainer(
		array $services = array(),
		array $containers = array()
	): Container {

		return new Container( $services, $containers );
	}
}
