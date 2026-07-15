<?php

declare(strict_types=1);

namespace ThemeZee\Packable\Tests\Unit\Container;

use ThemeZee\Packable\Container\ReadOnlyContainer as Container;
use ThemeZee\Packable\Tests\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class ReadOnlyContainerTest extends TestCase {

	/**
	 * @test
	 */
	public function testBasic(): void {
		$testee = $this->factoryContainer();

		static::assertInstanceOf( ContainerInterface::class, $testee );
		static::assertFalse( $testee->has( 'unknown' ) );
	}

	/**
	 * @test
	 */
	public function testGetUnknown(): void {
		static::expectException( NotFoundExceptionInterface::class );

		$testee = $this->factoryContainer();
		$testee->get( 'unknown' );
	}

	/**
	 * @test
	 * @dataProvider provideServices
	 *
	 * @param mixed    $expected
	 * @param callable $service
	 */
	public function testHasGetService( $expected, callable $service ): void {
		$expectedId = 'service';
		$services   = array( $expectedId => $service );
		$testee     = $this->factoryContainer( $services );

		// check in Services
		static::assertTrue( $testee->has( $expectedId ) );
		// resolve Service
		static::assertSame( $expected, $testee->get( $expectedId ) );
		// check in resolved Services
		static::assertTrue( $testee->has( $expectedId ) );
	}

	/**
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
	 * @test
	 */
	public function testHasGetServiceFromChildContainer(): void {
		$expectedKey   = 'service';
		$expectedValue = new \stdClass();

		$childContainer = new class($expectedKey, $expectedValue) implements ContainerInterface {
			/** @var array<string, \stdClass> */
			private array $data = array();

			public function __construct( string $key, \stdClass $value ) {
				$this->data[ $key ] = $value;
			}

			public function get( string $id ) {
				return $this->data[ $id ];
			}

			public function has( string $id ): bool {
				return isset( $this->data[ $id ] );
			}
		};

		$testee = $this->factoryContainer( array(), array( $childContainer ) );

		// check in child Container
		static::assertTrue( $testee->has( $expectedKey ) );
		// resolve Service
		static::assertSame( $expectedValue, $testee->get( $expectedKey ) );
		// check in resolved Services
		static::assertTrue( $testee->has( $expectedKey ) );
	}

	/**
	 * @test
	 */
	public function testServicesAreCached(): void {
		$expectedServiceKey = 'service';
		$services           = array(
			$expectedServiceKey => function (): object {
				return new class() {
					protected int $serviceCounter = 0;

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
	 * @param array<string, callable(ContainerInterface): mixed> $services
	 * @param ContainerInterface[]                               $containers
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
