<?php

declare(strict_types=1);

namespace ThemeZee\Packable\Tests\Unit\Module;

use ThemeZee\Packable;
use ThemeZee\Packable\Tests\TestCase;

class ModuleClassNameIdTraitTest extends TestCase {

	/**
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
