<?php
/**
 * Properties interface.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable\Properties;

interface Properties {

	public const PROP_AUTHOR       = 'author';
	public const PROP_AUTHOR_URI   = 'authorUri';
	public const PROP_DESCRIPTION  = 'description';
	public const PROP_DOMAIN_PATH  = 'domainPath';
	public const PROP_NAME         = 'name';
	public const PROP_TEXTDOMAIN   = 'textDomain';
	public const PROP_URI          = 'uri';
	public const PROP_VERSION      = 'version';
	public const PROP_REQUIRES_WP  = 'requiresWp';
	public const PROP_REQUIRES_PHP = 'requiresPhp';
	public const PROP_TAGS         = 'tags';

	public const DEFAULT_PROPERTIES = array(
		self::PROP_AUTHOR       => '',
		self::PROP_AUTHOR_URI   => '',
		self::PROP_DESCRIPTION  => '',
		self::PROP_DOMAIN_PATH  => '',
		self::PROP_NAME         => '',
		self::PROP_TEXTDOMAIN   => '',
		self::PROP_URI          => '',
		self::PROP_VERSION      => '',
		self::PROP_REQUIRES_WP  => null,
		self::PROP_REQUIRES_PHP => null,
		self::PROP_TAGS         => array(),
	);

	/**
	 * Returns the value for the given property key.
	 *
	 * @param string $key      Property key.
	 * @param mixed  $fallback Value returned when the key is not set.
	 * @return mixed
	 */
	public function get( string $key, $fallback = null );

	/**
	 * Returns whether the given property key is set.
	 *
	 * @param string $key Property key.
	 * @return bool
	 */
	public function has( string $key ): bool;

	/**
	 * Returns whether the application is in debug mode.
	 *
	 * @return bool
	 */
	public function isDebug(): bool;

	/**
	 * Returns the base name.
	 *
	 * @return string
	 */
	public function baseName(): string;

	/**
	 * Returns the base path.
	 *
	 * @return string
	 */
	public function basePath(): string;

	/**
	 * Returns the base URL, when known.
	 *
	 * @return string|null
	 */
	public function baseUrl(): ?string;

	/**
	 * Returns the author.
	 *
	 * @return string
	 */
	public function author(): string;

	/**
	 * Returns the author URI.
	 *
	 * @return string
	 */
	public function authorUri(): string;

	/**
	 * Returns the description.
	 *
	 * @return string
	 */
	public function description(): string;

	/**
	 * Returns the text domain.
	 *
	 * @return string
	 */
	public function textDomain(): string;

	/**
	 * Returns the domain path.
	 *
	 * @return string
	 */
	public function domainPath(): string;

	/**
	 * The name of the plugin, theme or library.
	 *
	 * @return string
	 */
	public function name(): string;

	/**
	 * The home page of the plugin, theme or library.
	 *
	 * @return string
	 */
	public function uri(): string;

	/**
	 * Returns the version.
	 *
	 * @return string
	 */
	public function version(): string;

	/**
	 * Optional. Specify the minimum required WordPress version.
	 *
	 * @return string|null
	 */
	public function requiresWp(): ?string;

	/**
	 * Optional. Specify the minimum required PHP version.
	 *
	 * @return string|null
	 */
	public function requiresPhp(): ?string;

	/**
	 * Optional. Currently, only available for Theme and Library.
	 * Plugins do not have support for "tags"/"keywords" in header.
	 *
	 * @return string[]
	 *
	 * @see https://developer.wordpress.org/reference/classes/wp_theme/#properties
	 * @see https://getcomposer.org/doc/04-schema.md#keywords
	 */
	public function tags(): array;
}
