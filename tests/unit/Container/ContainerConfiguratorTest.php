<?php
/**
 * Tests for ContainerConfigurator.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable\Tests\Unit\Container;

use ThemeZee\Packable\Container\ContainerConfigurator;
use ThemeZee\Packable\Tests\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Tests registering services and building the container.
 */
class ContainerConfiguratorTest extends TestCase {

	/**
	 * Tests the configurator basics.
	 *
	 * @test
	 */
	public function testBasic(): void {
		$testee = new ContainerConfigurator();

		static::assertInstanceOf( ContainerConfigurator::class, $testee );
		static::assertFalse( $testee->hasService( 'something' ) );
		static::assertInstanceOf( ContainerInterface::class, $testee->createReadOnlyContainer() );
	}

	/**
	 * Tests adding and checking a service.
	 *
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
			static function () use ( $expectedValue ) {
				return $expectedValue;
			}
		);

		static::assertTrue( $testee->hasService( $expectedKey ) );
	}

	/**
	 * Tests that a later service registration overrides an earlier one.
	 *
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
	 * Tests that an unknown service is reported as missing.
	 *
	 * @test
	 */
	public function testHasServiceNotFound(): void {
		$testee = new ContainerConfigurator();
		static::assertFalse( $testee->hasService( 'unknown-service' ) );
	}

	/**
	 * Tests looking up a service provided by a child container.
	 *
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
	 * Tests registering and resolving from a custom child container.
	 *
	 * @test
	 */
	public function testCustomContainer(): void {
		$expectedId    = 'expected-id';
		$expectedValue = new \stdClass();

		$childContainer = new class($expectedId, $expectedValue) implements ContainerInterface
		{
			/**
			 * Values keyed by id.
			 *
			 * @var array<string, object>
			 */
			private array $values = array();

			/**
			 * Constructor.
			 *
			 * @param string $expectedId    Value id.
			 * @param object $expectedValue Value to store.
			 */
			public function __construct( string $expectedId, object $expectedValue ) {
				$this->values[ $expectedId ] = $expectedValue;
			}

			/**
			 * Returns the value for the given id.
			 *
			 * @param string $id Value id.
			 * @return mixed
			 */
			public function get( string $id ) {
				return $this->values[ $id ];
			}

			/**
			 * Returns whether a value with the given id exists.
			 *
			 * @param string $id Value id.
			 * @return bool
			 */
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
