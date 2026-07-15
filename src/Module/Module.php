<?php

declare(strict_types=1);

namespace ThemeZee\Packable\Module;

/**
 * @package ThemeZee\Packable\Module
 */
interface Module {

	/**
	 * Unique identifier for your Module.
	 *
	 * @return string
	 */
	public function id(): string;
}
