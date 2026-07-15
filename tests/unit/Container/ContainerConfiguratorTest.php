<?php

declare(strict_types=1);

namespace ThemeZee\Packable\Tests\Unit\Container;

use ThemeZee\Packable\Container\ContainerConfigurator;
use ThemeZee\Packable\Tests\TestCase;
use Psr\Container\ContainerInterface;

class ContainerConfiguratorTest extends TestCase {

	/**
	 * @test
	 */
	public function testBasic(): void {
		$testee = new ContainerConfigurator();

		static::assertInstanceOf( ContainerConfigurator::class, $testee );
		static::assertFalse( $testee->hasService( 'something' ) );
		static::assertInstanceOf( ContainerInterface::class, $testee->createReadOnlyContainer() );
	}

	/**
	 * @test
	 */
	public function testAddHasService(): void {
		$expectedKey   = 'key';
		$expectedValue = new class() {
		};

		$testee = new ContainerConfigurator();

		static::assertFalse( $testee->hasService( $expectedKey ) );

		$testee->addService(
			$expectedKey,
			/** @return mixed */
			static function () use ( $expectedValue ) {
				return $expectedValue;
			}
		);

		static::assertTrue( $testee->hasService( $expectedKey ) );
	}

	/**
	 * @test
	 */
	public function testServiceOverride(): void {
		$expectedKey = 'key';

		$testee = new ContainerConfigurator();
		$testee->addService(
			$expectedKey,
			static function (): \DateTime {
				return new \DateTime();
			}
		);
		$testee->addService(
			$expectedKey,
			static function (): \DateTimeImmutable {
				return new \DateTimeImmutable();
			}
		);
		$container = $testee->createReadOnlyContainer();
		$result    = $container->get( $expectedKey );

		self::assertInstanceOf( \DateTimeImmutable::class, $result );
	}

	/**
	 * @test
	 */
	public function testHasServiceNotFound(): void {
		$testee = new ContainerConfigurator();
		static::assertFalse( $testee->hasService( 'unknown-service' ) );
	}

	/**
	 * @test
	 */
	public function testHasServiceInChildContainer(): void {
		$expectedKey    = 'key';
		$childContainer = $this->stubContainer( $expectedKey );

		$testee = new ContainerConfigurator();
		$testee->addContainer( $childContainer );

		static::assertTrue( $testee->hasService( $expectedKey ) );
	}

	/**
	 * @test
	 */
	public function testCustomContainer(): void {
		$expectedId    = 'expected-id';
		$expectedValue = new \stdClass();

		$childContainer = new class($expectedId, $expectedValue) implements ContainerInterface
		{
			/** @var array<string, object> */
			private array $values = array();

			public function __construct( string $expectedId, object $expectedValue ) {
				$this->values[ $expectedId ] = $expectedValue;
			}

			public function get( string $id ) {
				return $this->values[ $id ];
			}

			public function has( string $id ): bool {
				return isset( $this->values[ $id ] );
			}
		};

		$testee = new ContainerConfigurator( array( $childContainer ) );

		static::assertTrue( $testee->hasService( $expectedId ) );

		$readOnlyContainer = $testee->createReadOnlyContainer();
		static::assertSame( $expectedValue, $readOnlyContainer->get( $expectedId ) );
	}
}
