<?php

declare(strict_types=1);

namespace ThemeZee\Packable\Tests\Unit\Properties;

use ThemeZee\Packable\Properties\BaseProperties;
use ThemeZee\Packable\Properties\Properties;
use ThemeZee\Packable\Tests\TestCase;

class BasePropertiesTest extends TestCase {

	/**
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

		// Defaults
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
	 * @test
	 * @dataProvider provideBaseNameData
	 */
	public function testBaseNameSanitization( string $baseName, string $sanitizedBaseName ): void {
		$testee = $this->factoryBaseProperties(
			$baseName,
			''
		);

		static::assertSame( $sanitizedBaseName, $testee->baseName() );
	}

	/**
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
	 * @param string                $baseName
	 * @param string                $basePath
	 * @param string|null           $baseUrl
	 * @param array<string, string> $properties
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
			public function __construct(
				string $baseName,
				string $basePath,
				?string $baseUrl = null,
				array $properties = array()
			) {

				parent::__construct( $baseName, $basePath, $baseUrl, $properties );
			}
		};
	}
}
