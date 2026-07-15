<?php
/**
 * Module interface for modules that run behaviour during boot.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable\Module;

use Psr\Container\ContainerInterface;

interface ExecutableModule extends Module {

	/**
	 * Perform actions with objects retrieved from the container. Usually, adding WordPress hooks.
	 * Return true to signal a success, false to signal a failure.
	 *
	 * @param ContainerInterface $container Compiled container to resolve services from.
	 *
	 * @return bool     true when successfully booted, otherwise false.
	 */
	public function run( ContainerInterface $container ): bool;
}
