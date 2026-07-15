<?php
/**
 * Properties implementation for plugins.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable\Properties;

/**
 * Builds Properties from a plugin's file headers.
 */
class PluginProperties extends BaseProperties {

	// Custom properties for Plugins.
	public const PROP_NETWORK          = 'network';
	public const PROP_REQUIRES_PLUGINS = 'requiresPlugins';

	/**
	 * Plugin header keys mapped to property keys.
	 *
	 * @see https://developer.wordpress.org/reference/functions/get_plugin_data/
	 */
	protected const HEADERS = array(
		self::PROP_AUTHOR           => 'Author',
		self::PROP_AUTHOR_URI       => 'AuthorURI',
		self::PROP_DESCRIPTION      => 'Description',
		self::PROP_DOMAIN_PATH      => 'DomainPath',
		self::PROP_NAME             => 'Name',
		self::PROP_TEXTDOMAIN       => 'TextDomain',
		self::PROP_URI              => 'PluginURI',
		self::PROP_VERSION          => 'Version',
		self::PROP_REQUIRES_WP      => 'RequiresWP',
		self::PROP_REQUIRES_PHP     => 'RequiresPHP',

		// additional headers.
		self::PROP_NETWORK          => 'Network',
		self::PROP_REQUIRES_PLUGINS => 'RequiresPlugins',
	);

	/**
	 * Absolute path to the plugin main file.
	 *
	 * @var string
	 */
	private string $pluginMainFile;

	/**
	 * Plugin base name.
	 *
	 * @var string
	 */
	private string $pluginBaseName;

	/**
	 * Cached must-use flag.
	 *
	 * @var bool|null
	 */
	protected ?bool $isMu = null;

	/**
	 * Cached active flag.
	 *
	 * @var bool|null
	 */
	protected ?bool $isActive = null;

	/**
	 * Cached network-active flag.
	 *
	 * @var bool|null
	 */
	protected ?bool $isNetworkActive = null;

	/**
	 * Creates Properties from the given plugin main file.
	 *
	 * @param string $pluginMainFile Absolute path to the plugin main file.
	 * @return PluginProperties
	 */
	public static function new( string $pluginMainFile ): PluginProperties {
		return new self( $pluginMainFile );
	}

	/**
	 * Constructor.
	 *
	 * @param string $pluginMainFile Absolute path to the plugin main file.
	 */
	protected function __construct( string $pluginMainFile ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		// $markup = false, to avoid an incorrect early wptexturize call.
		// $translate = false, to avoid loading translations too early
		// @see https://core.trac.wordpress.org/ticket/49965
		// @see https://core.trac.wordpress.org/ticket/34114
		$pluginData = (array) get_plugin_data( $pluginMainFile, false, false );
		$properties = Properties::DEFAULT_PROPERTIES;

		// Map pluginData to internal structure.
		foreach ( self::HEADERS as $key => $pluginDataKey ) {
			$properties[ $key ] = $pluginData[ $pluginDataKey ] ?? '';
			unset( $pluginData[ $pluginDataKey ] );
		}
		$properties = array_merge( $properties, $pluginData );

		$this->pluginMainFile = wp_normalize_path( $pluginMainFile );

		$this->pluginBaseName = plugin_basename( $pluginMainFile );
		$basePath             = plugin_dir_path( $pluginMainFile );
		$baseUrl              = plugins_url( '/', $pluginMainFile );

		parent::__construct(
			$this->pluginBaseName,
			$basePath,
			$baseUrl,
			$properties
		);
	}

	/**
	 * Returns the absolute path to the plugin main file.
	 *
	 * @return string
	 */
	public function pluginMainFile(): string {
		return $this->pluginMainFile;
	}

	/**
	 * Returns whether the plugin is network-only.
	 *
	 * @return bool
	 */
	public function network(): bool {
		return (bool) $this->get( self::PROP_NETWORK, false );
	}

	/**
	 * Returns the list of required plugins.
	 *
	 * @return string[]
	 */
	public function requiresPlugins(): array {
		$value = $this->get( self::PROP_REQUIRES_PLUGINS );

		return $value && is_string( $value ) ? explode( ',', $value ) : array();
	}

	/**
	 * Returns whether the plugin is active.
	 *
	 * @return bool
	 */
	public function isActive(): bool {
		if ( null === $this->isActive ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$this->isActive = is_plugin_active( $this->pluginBaseName );
		}

		return $this->isActive;
	}

	/**
	 * Returns whether the plugin is network active.
	 *
	 * @return bool
	 */
	public function isNetworkActive(): bool {
		if ( null === $this->isNetworkActive ) {
			if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$this->isNetworkActive = is_plugin_active_for_network( $this->pluginBaseName );
		}

		return $this->isNetworkActive;
	}

	/**
	 * Returns whether the plugin is a must-use plugin.
	 *
	 * @return bool
	 */
	public function isMuPlugin(): bool {
		if ( null === $this->isMu ) {
			$muPluginDir = wp_normalize_path( WPMU_PLUGIN_DIR );
			$this->isMu  = 0 === strpos( $this->pluginMainFile, $muPluginDir );
		}

		return $this->isMu;
	}
}
