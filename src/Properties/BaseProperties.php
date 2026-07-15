<?php
/**
 * Base implementation of the Properties interface.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable\Properties;

/**
 * Shared Properties implementation backed by an array of values.
 */
class BaseProperties implements Properties {

	/**
	 * Cached debug flag.
	 *
	 * @var bool|null
	 */
	protected ?bool $isDebug = null;

	/**
	 * Base name.
	 *
	 * @var string
	 */
	protected string $baseName;

	/**
	 * Base path.
	 *
	 * @var string
	 */
	protected string $basePath;

	/**
	 * Base URL, when known.
	 *
	 * @var string|null
	 */
	protected ?string $baseUrl;

	/**
	 * Property values, keyed by property key.
	 *
	 * @var array<string, mixed>
	 */
	protected array $properties;

	/**
	 * Constructor.
	 *
	 * @param string               $baseName   Base name.
	 * @param string               $basePath   Base path.
	 * @param string|null          $baseUrl    Base URL, when known.
	 * @param array<string, mixed> $properties Property values.
	 */
	protected function __construct(
		string $baseName,
		string $basePath,
		?string $baseUrl = null,
		array $properties = array()
	) {

		$baseName = $this->sanitizeBaseName( $baseName );
		$basePath = trailingslashit( $basePath );
		if ( null !== $baseUrl ) {
			$baseUrl = trailingslashit( $baseUrl );
		}

		$this->baseName   = $baseName;
		$this->basePath   = $basePath;
		$this->baseUrl    = $baseUrl;
		$this->properties = array_replace( Properties::DEFAULT_PROPERTIES, $properties );
	}

	/**
	 * Normalises a raw base name to a lowercase file name.
	 *
	 * @param string $name Raw base name.
	 *
	 * @return lowercase-string
	 */
	protected function sanitizeBaseName( string $name ): string {
		if ( substr_count( $name, '/' ) ) {
			$name = dirname( $name );
		}

		return strtolower( pathinfo( $name, PATHINFO_FILENAME ) );
	}

	/**
	 * Returns the base name.
	 *
	 * @return string
	 */
	public function baseName(): string {
		return $this->baseName;
	}

	/**
	 * Returns the base path.
	 *
	 * @return string
	 */
	public function basePath(): string {
		return $this->basePath;
	}

	/**
	 * Returns the base URL, when known.
	 *
	 * @return string|null
	 */
	public function baseUrl(): ?string {
		return $this->baseUrl;
	}

	/**
	 * Returns the author.
	 *
	 * @return string
	 */
	public function author(): string {
		return (string) $this->get( self::PROP_AUTHOR );
	}

	/**
	 * Returns the author URI.
	 *
	 * @return string
	 */
	public function authorUri(): string {
		return (string) $this->get( self::PROP_AUTHOR_URI );
	}

	/**
	 * Returns the description.
	 *
	 * @return string
	 */
	public function description(): string {
		return (string) $this->get( self::PROP_DESCRIPTION );
	}

	/**
	 * Returns the text domain.
	 *
	 * @return string
	 */
	public function textDomain(): string {
		return (string) $this->get( self::PROP_TEXTDOMAIN );
	}

	/**
	 * Returns the domain path.
	 *
	 * @return string
	 */
	public function domainPath(): string {
		return (string) $this->get( self::PROP_DOMAIN_PATH );
	}

	/**
	 * Returns the name.
	 *
	 * @return string
	 */
	public function name(): string {
		return (string) $this->get( self::PROP_NAME );
	}

	/**
	 * Returns the home page URI.
	 *
	 * @return string
	 */
	public function uri(): string {
		return (string) $this->get( self::PROP_URI );
	}

	/**
	 * Returns the version.
	 *
	 * @return string
	 */
	public function version(): string {
		return (string) $this->get( self::PROP_VERSION );
	}

	/**
	 * Returns the minimum required WordPress version, when set.
	 *
	 * @return string|null
	 */
	public function requiresWp(): ?string {
		$value = $this->get( self::PROP_REQUIRES_WP );

		return ( ( '' !== $value ) && is_string( $value ) )
			? $value
			: null;
	}

	/**
	 * Returns the minimum required PHP version, when set.
	 *
	 * @return string|null
	 */
	public function requiresPhp(): ?string {
		$value = $this->get( self::PROP_REQUIRES_PHP );

		return ( ( '' !== $value ) && is_string( $value ) )
			? $value
			: null;
	}

	/**
	 * Returns the tags/keywords.
	 *
	 * @return string[]
	 */
	public function tags(): array {
		return (array) $this->get( self::PROP_TAGS );
	}

	/**
	 * Returns the value for the given property key.
	 *
	 * @param string $key      Property key.
	 * @param mixed  $fallback Value returned when the key is not set.
	 *
	 * @return mixed
	 */
	public function get( string $key, $fallback = null ) {
		return $this->properties[ $key ] ?? $fallback;
	}

	/**
	 * Returns whether the given property key is set.
	 *
	 * @param string $key Property key.
	 *
	 * @return bool
	 */
	public function has( string $key ): bool {
		return isset( $this->properties[ $key ] );
	}

	/**
	 * Returns whether the application is in debug mode.
	 *
	 * @return bool
	 * @see Properties::isDebug()
	 */
	public function isDebug(): bool {
		if ( null === $this->isDebug ) {
			$this->isDebug = defined( 'WP_DEBUG' ) && WP_DEBUG;
		}

		return $this->isDebug;
	}
}
