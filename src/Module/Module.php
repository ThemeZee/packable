<?php
/**
 * Base module interface.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable\Module;

/**
 * Base interface all modules must implement.
 */
interface Module {

	/**
	 * Unique identifier for your Module.
	 *
	 * @return string
	 */
	public function id(): string;
}
