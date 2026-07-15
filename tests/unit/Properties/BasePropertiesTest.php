<?php
/**
 * Tests for BaseProperties.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable\Tests\Unit\Properties;

use ThemeZee\Packable\Properties\BaseProperties;
use ThemeZee\Packable\Properties\Properties;
use ThemeZee\Packable\Tests\TestCase;

/**
 * Tests the shared BaseProperties behaviour.
 */
class BasePropertiesTest extends TestCase {

	/**
	 * Tests default property values.
	 *
	 * @test
	 */
	public function testBasic(): void {
		$expectedName = 'foo';
		$expectedPath = __DIR__ . '/';

		$testee = $this->factoryBaseProperties(
			$expectedName,
			$expectedPath
		);

		static::assertInstanceOf( Properties::class, $testee );
		static::assertSame( $expectedName, $testee->baseName() );
		static::assertSame( $expectedPath, $testee->basePath() );

		// Defaults.
		static::assertFalse( $testee->isDebug() );
		static::assertEmpty( $testee->tags() );
		static::assertFalse( $testee->has( 'unknown-key' ) );
		static::assertSame( null, $testee->baseUrl() );
		static::assertSame( '', $testee->author() );
		static::assertSame( '', $testee->authorUri() );
		static::assertSame( '', $testee->description() );
		static::assertSame( '', $testee->domainPath() );
		static::assertSame( '', $testee->name() );
		static::assertSame( '', $testee->uri() );
		static::assertSame( '', $testee->version() );
		static::assertSame( null, $testee->requiresPhp() );
		static::assertSame( null, $testee->requiresWp() );
	}

	/**
	 * Tests base-name sanitization.
	 *
	 * @test
	 * @dataProvider provideBaseNameData
	 *
	 * @param string $baseName          Raw base name.
	 * @param string $sanitizedBaseName Expected sanitized base name.
	 */
	public function testBaseNameSanitization( string $baseName, string $sanitizedBaseName ): void {
		$testee = $this->factoryBaseProperties(
			$baseName,
			''
		);

		static::assertSame( $sanitizedBaseName, $testee->baseName() );
	}

	/**
	 * Provides raw base names and their sanitized form.
	 *
	 * @return \Generator
	 */
	public static function provideBaseNameData(): \Generator {
		yield 'empty' => array( '', '' );
		yield 'word' => array( 'foo', 'foo' );
		yield 'words' => array( 'foo bar', 'foo bar' );
		yield 'relative path to package' => array( 'path/package-dir/package.php', 'package-dir' );
		yield 'absolute path' => array( '/abs/path/package-dir/package.php', 'package-dir' );
		yield 'single file' => array( 'package.php', 'package' );
	}

	/**
	 * Builds a concrete BaseProperties instance for testing.
	 *
	 * @param string                $baseName   Base name.
	 * @param string                $basePath   Base path.
	 * @param string|null           $baseUrl    Base URL, when known.
	 * @param array<string, string> $properties Property values.
	 * @return BaseProperties
	 */
	private function factoryBaseProperties(
		string $baseName,
		string $basePath,
		?string $baseUrl = null,
		array $properties = array()
	): BaseProperties {

		return new class($baseName, $basePath, $baseUrl, $properties) extends BaseProperties
		{
			// phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found -- Exposes the protected parent constructor for testing.
			/**
			 * Constructor.
			 *
			 * @param string                $baseName   Base name.
			 * @param string                $basePath   Base path.
			 * @param string|null           $baseUrl    Base URL, when known.
			 * @param array<string, string> $properties Property values.
			 */
			public function __construct(
				string $baseName,
				string $basePath,
				?string $baseUrl = null,
				array $properties = array()
			) {

				parent::__construct( $baseName, $basePath, $baseUrl, $properties );
			}
			// phpcs:enable Generic.CodeAnalysis.UselessOverridingMethod.Found
		};
	}
}
