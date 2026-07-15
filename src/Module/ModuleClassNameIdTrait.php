<?php
/**
 * Trait deriving a module id from its class name.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable\Module;

trait ModuleClassNameIdTrait {

	/**
	 * Returns the module id, using the implementing class name.
	 *
	 * @return string
	 *
	 * @see Module::id()
	 */
	public function id(): string {
		return __CLASS__;
	}
}
