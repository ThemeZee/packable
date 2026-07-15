<?php
/**
 * Tests for the ModuleClassNameIdTrait.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable\Tests\Unit\Module;

use ThemeZee\Packable;
use ThemeZee\Packable\Tests\TestCase;

/**
 * Tests that the trait derives a module id from the class name.
 */
class ModuleClassNameIdTraitTest extends TestCase {

	/**
	 * Tests that the module id matches the class name.
	 *
	 * @test
	 */
	public function testIdMatchesClassName(): void {
		$module = new class() implements Packable\Module\Module
		{
			use Packable\Module\ModuleClassNameIdTrait;
		};

		static::assertSame( get_class( $module ), $module->id() );
	}
}
