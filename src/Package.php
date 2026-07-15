<?php
/**
 * Main package class that bootstraps modules and the container.
 *
 * @package ThemeZee\Packable
 */

declare(strict_types=1);

namespace ThemeZee\Packable;

use ThemeZee\Packable\Container\ContainerConfigurator;
use ThemeZee\Packable\Module\ExecutableModule;
use ThemeZee\Packable\Module\Module;
use ThemeZee\Packable\Module\ServiceModule;
use ThemeZee\Packable\Properties\Properties;
use Psr\Container\ContainerInterface;

/**
 * Manages modules, the service container and the build/boot lifecycle.
 */
class Package {

	/**
	 * All the hooks fired in this class use this prefix.
	 */
	private const HOOK_PREFIX = 'themezee.packable.';

	/**
	 * Identifier to access Properties in Container.
	 *
	 * @example
	 * <code>
	 * $package = Package::new();
	 * $package->boot();
	 *
	 * $container = $package->container();
	 * $container->has(Package::PROPERTIES);
	 * $container->get(Package::PROPERTIES);
	 * </code>
	 */
	public const PROPERTIES = 'properties';

	/**
	 * Custom action to be used to add modules.
	 * It might also be used to access package properties.
	 * Access container is not possible at this stage.
	 *
	 * @example
	 * <code>
	 * $package = Package::new();
	 *
	 * add_action(
	 *      $package->hookName(Package::ACTION_INIT),
	 *      fn (Package $package) => // do something,
	 * );
	 * </code>
	 */
	public const ACTION_INIT = 'init';

	/**
	 * Very similar to `ACTION_INIT`, but it is static, so not dependent on package name.
	 * It passes package name as first argument.
	 *
	 * @example
	 *  <code>
	 *  add_action(
	 *       Package::ACTION_PACKABLE_INIT,
	 *       fn (string $packageName, Package $package) => // do something,
	 *       10,
	 *       2
	 *  );
	 *  </code>
	 */
	public const ACTION_PACKABLE_INIT = self::HOOK_PREFIX . self::ACTION_INIT;

	/**
	 * Action fired when it is safe to access container.
	 * Add more modules is not anymore possible at this stage.
	 */
	public const ACTION_INITIALIZED = 'initialized';

	/**
	 * Action fired when plugin finished its bootstrapping process, all its hooks are added.
	 * Add more modules is not anymore possible at this stage.
	 */
	public const ACTION_BOOTED = 'ready';

	/**
	 * Action fired when anything went wrong during the "build" procedure.
	 */
	public const ACTION_FAILED_BUILD = 'failed-build';

	/**
	 * Action fired when anything went wrong during the "boot" procedure.
	 */
	public const ACTION_FAILED_BOOT = 'failed-boot';

	/**
	 * Action fired when adding a module failed.
	 */
	public const ACTION_FAILED_ADD_MODULE = 'failed-add-module';

	/**
	 * Module states can be used to get information about your module.
	 *
	 * @example
	 * <code>
	 * $package = Package::new();
	 * $package->moduleIs(SomeModule::class, Package::MODULE_ADDED); // false
	 * $package->addModule(new SomeModule());
	 * $package->moduleIs(SomeModule::class, Package::MODULE_ADDED); // true
	 * </code>
	 */
	public const MODULE_ADDED            = 'added';
	public const MODULE_NOT_ADDED        = 'not-added';
	public const MODULE_REGISTERED       = 'registered';
	public const MODULE_EXECUTED         = 'executed';
	public const MODULE_EXECUTION_FAILED = 'executed-failed';
	public const MODULES_ALL             = '*';

	/**
	 * Custom states for the class.
	 *
	 * @example
	 * <code>
	 * $package = Package::new();
	 * $package->statusIs(Package::STATUS_IDLE); // true
	 * $package->build();
	 * $package->statusIs(Package::STATUS_INITIALIZED); // true
	 * $package->boot();
	 * $package->statusIs(Package::STATUS_DONE); // true
	 * </code>
	 */
	public const STATUS_IDLE         = 2;
	public const STATUS_INITIALIZING = 3;
	public const STATUS_INITIALIZED  = 4;
	public const STATUS_BOOTING      = 5;
	public const STATUS_BOOTED       = 7;
	public const STATUS_DONE         = 8;
	public const STATUS_FAILED       = -8;

	// Map of status to package-specific and global hook, both optional (i..e, null).
	private const STATUSES_ACTIONS_MAP = array(
		self::STATUS_INITIALIZING => array( self::ACTION_INIT, self::ACTION_PACKABLE_INIT ),
		self::STATUS_INITIALIZED  => array( self::ACTION_INITIALIZED, null ),
		self::STATUS_BOOTED       => array( self::ACTION_BOOTED, null ),
	);

	private const SUCCESS_STATUSES = array(
		self::STATUS_IDLE         => self::STATUS_IDLE,
		self::STATUS_INITIALIZING => self::STATUS_INITIALIZING,
		self::STATUS_INITIALIZED  => self::STATUS_INITIALIZED,
		self::STATUS_BOOTING      => self::STATUS_BOOTING,
		self::STATUS_BOOTED       => self::STATUS_BOOTED,
		self::STATUS_DONE         => self::STATUS_DONE,
	);

	private const OPERATORS = array(
		'<'  => '<',
		'<=' => '<=',
		'>'  => '>',
		'>=' => '>=',
		'==' => '==',
		'!=' => '!=',
	);

	/**
	 * Current status of the package.
	 *
	 * @var Package::STATUS_*
	 */
	private int $status = self::STATUS_IDLE;

	/**
	 * Recorded status per module, plus the aggregate list.
	 *
	 * @var array<string, list<string>>
	 */
	private array $moduleStatus = array( self::MODULES_ALL => array() );

	/**
	 * Executable modules collected to be run on boot.
	 *
	 * @var list<ExecutableModule>
	 */
	private array $executables = array();

	/**
	 * Package properties.
	 *
	 * @var Properties
	 */
	private Properties $properties;

	/**
	 * Configurator used to collect services and build the container.
	 *
	 * @var ContainerConfigurator
	 */
	private ContainerConfigurator $containerConfigurator;

	/**
	 * Whether build() has already been called.
	 *
	 * @var bool
	 */
	private bool $built = false;

	/**
	 * Whether the compiled container has been requested.
	 *
	 * @var bool
	 */
	private bool $hasContainer = false;

	/**
	 * Last caught error, when not in debug mode.
	 *
	 * @var \Throwable|null
	 */
	private ?\Throwable $lastError = null;

	/**
	 * Creates a new package instance.
	 *
	 * @param Properties         $properties    Package properties.
	 * @param ContainerInterface ...$containers Optional child containers to delegate to.
	 * @return Package
	 */
	public static function new( Properties $properties, ContainerInterface ...$containers ): Package {
		return new self( $properties, ...$containers );
	}

	/**
	 * Constructor.
	 *
	 * @param Properties         $properties    Package properties.
	 * @param ContainerInterface ...$containers Optional child containers to delegate to.
	 */
	private function __construct( Properties $properties, ContainerInterface ...$containers ) {
		$this->properties = $properties;

		$this->containerConfigurator = new ContainerConfigurator( $containers );
		$this->containerConfigurator->addService(
			self::PROPERTIES,
			static function () use ( $properties ): Properties {
				return $properties;
			}
		);
	}

	/**
	 * Adds a module to the package.
	 *
	 * @param Module $module Module to add.
	 * @return static
	 */
	public function addModule( Module $module ): Package {
		try {
			$reason = sprintf( 'add module %s', $module->id() );
			$this->assertStatus( self::STATUS_FAILED, $reason, '!=' );
			$this->assertStatus( self::STATUS_INITIALIZING, $reason, '<=' );

			$registeredServices = $this->addModuleServices( $module );
			$isExecutable       = $module instanceof ExecutableModule;

			// ExecutableModules are collected and executed on Package::boot()
			// when the Container is being compiled.
			if ( $isExecutable ) {
				$this->executables[] = $module;
			}

			$added  = $registeredServices || $isExecutable;
			$status = $added ? self::MODULE_ADDED : self::MODULE_NOT_ADDED;
			$this->moduleProgress( $module->id(), $status );
		} catch ( \Throwable $throwable ) {
			$this->handleFailure( $throwable, self::ACTION_FAILED_ADD_MODULE );
		}

		return $this;
	}

	/**
	 * Runs the "build" phase, locking the container from further changes.
	 *
	 * @return static
	 */
	public function build(): Package {
		try {
			// Be tolerant about things like `$package->build()->build()`.
			// Sometimes, from the extern, we might want to call `build()` to ensure the container
			// is ready before accessing a service. And in that case we don't want to throw an
			// exception if the container is already built.
			if ( $this->built && $this->statusIs( self::STATUS_INITIALIZED ) ) {
				return $this;
			}

			// We expect `build` to be called only after `addModule()` which does not change the
			// status, so we expect status to be still "IDLE".
			// This will prevent invalid things like calling `build()` from inside something
			// hooking ACTION_INIT OR ACTION_INITIALIZED.
			$this->assertStatus( self::STATUS_IDLE, 'build package' );

			// This will change the status to "INITIALIZING" then fire the action that allow other
			// code to add modules.
			$this->progress( self::STATUS_INITIALIZING );

			// This will change the status to "INITIALIZED" then fire an action when it is safe to
			// access the container, because from this moment on, container is locked from change.
			$this->progress( self::STATUS_INITIALIZED );
		} catch ( \Throwable $throwable ) {
			$this->handleFailure( $throwable, self::ACTION_FAILED_BUILD );
		} finally {
			$this->built = true;
		}

		return $this;
	}

	/**
	 * Runs the "boot" phase, executing modules after building if needed.
	 *
	 * @return bool
	 */
	public function boot(): bool {
		try {
			// When package is done, nothing should happen to it calling boot again, but we call
			// false to signal something is off.
			if ( $this->statusIs( self::STATUS_DONE ) ) {
				return false;
			}

			// Call build() if not called yet.
			$this->doBuild();

			// Make sure we call boot() on a non-failed instance, and also make a sanity check
			// on the status flow, e.g. prevent calling boot() from an action hook.
			$this->assertStatus( self::STATUS_INITIALIZED, 'boot application' );

			// This will change status to STATUS_BOOTING "locking" subsequent call to `boot()`, but
			// no hook is fired here, because at this point we can not do anything more or less than
			// what can be done on the ACTION_INITIALIZED hook, so that hook is sufficient.
			$this->progress( self::STATUS_BOOTING );

			$this->doExecute();

			// This will change status to STATUS_BOOTED and then fire an action that make it
			// possible to hook on a package that has finished its bootstrapping process, so all its
			// "executable" modules have been executed.
			$this->progress( self::STATUS_BOOTED );
		} catch ( \Throwable $throwable ) {
			$this->handleFailure( $throwable, self::ACTION_FAILED_BOOT );

			return false;
		}

		// This will change the status to DONE and will not fire any action.
		// This is a status that proves that everything went well, not only the Package itself,
		// but also anything hooking Package's hooks.
		// The only way to move out of this status is a failure that might only happen directly
		// calling `addModule()` or `build()`.
		$this->progress( self::STATUS_DONE );

		return true;
	}

	/**
	 * Calls build() when boot() is invoked before an explicit build.
	 *
	 * @return void
	 */
	private function doBuild(): void {
		// We expect `boot()` to be called either:
		// 1. Directly after `addModule()`, without any `build()` call in between, so
		// status is IDLE and `$this->built` is `false`. In that case we call `build()` here.
		// 2. After `build()` was already called, so status is INITIALIZED and `$this->built` is
		// `true`. In that case there is nothing left to do.
		// Any other usage is not allowed (e.g. calling `boot()` from an hook callback). We do
		// nothing and hand control back to `boot()`, which will throw via `assertStatus()`.
		if ( ! $this->built && $this->statusIs( self::STATUS_IDLE ) ) {
			$this->build();
		}
	}

	/**
	 * Registers the services provided by a module, if any.
	 *
	 * @param Module $module Module to register services from.
	 * @return bool
	 */
	private function addModuleServices( Module $module ): bool {
		$services = $module instanceof ServiceModule ? $module->services() : null;

		if ( ( null === $services ) || ( array() === $services ) ) {
			return false;
		}

		$ids = array();
		foreach ( $services as $id => $service ) {
			$this->containerConfigurator->addService( $id, $service );
			$ids[] = $id;
		}

		$this->moduleProgress( $module->id(), self::MODULE_REGISTERED, $ids );

		return true;
	}

	/**
	 * Runs every collected executable module.
	 *
	 * @return void
	 */
	private function doExecute(): void {
		foreach ( $this->executables as $executable ) {
			$success = $executable->run( $this->container() );
			$this->moduleProgress(
				$executable->id(),
				$success ? self::MODULE_EXECUTED : self::MODULE_EXECUTION_FAILED,
			);
		}
	}

	/**
	 * Records the status reached by a module.
	 *
	 * @param string            $moduleId   Module id.
	 * @param string            $status     Status constant reached.
	 * @param list<string>|null $serviceIds Ids of the services involved, when debugging.
	 * @return void
	 */
	private function moduleProgress(
		string $moduleId,
		string $status,
		?array $serviceIds = null
	): void {

		if ( ! isset( $this->moduleStatus[ $status ] ) ) {
			$this->moduleStatus[ $status ] = array();
		}
		$this->moduleStatus[ $status ][] = $moduleId;

		if ( ( null === $serviceIds ) || ( array() === $serviceIds ) || ! $this->properties->isDebug() ) {
			$this->moduleStatus[ self::MODULES_ALL ][] = "{$moduleId} {$status}";

			return;
		}

		$description                               = sprintf( '%s %s (%s)', $moduleId, $status, implode( ', ', $serviceIds ) );
		$this->moduleStatus[ self::MODULES_ALL ][] = $description;
	}

	/**
	 * Returns the recorded status map for all modules.
	 *
	 * @return array<string, list<string>>
	 */
	public function modulesStatus(): array {
		return $this->moduleStatus;
	}

	/**
	 * Checks whether a module reached the given status.
	 *
	 * @param string $moduleId Module id.
	 * @param string $status   Status constant to check.
	 *
	 * @return bool
	 */
	public function moduleIs( string $moduleId, string $status ): bool {
		return in_array( $moduleId, $this->moduleStatus[ $status ] ?? array(), true );
	}

	/**
	 * Return the filter name to be used to extend modules of the plugin.
	 *
	 * If the plugin is single file `my-plugin.php` in plugins folder the filter name will be:
	 * `themezee.packable.my-plugin`.
	 *
	 * If the plugin is in a sub-folder e.g. `my-plugin/index.php` the filter name will be:
	 * `themezee.packable.my-plugin` anyway, so the file name is not relevant.
	 *
	 * @param string $suffix Optional hook suffix to append.
	 * @return string
	 *
	 * @see Package::name()
	 */
	public function hookName( string $suffix = '' ): string {
		$filter = self::HOOK_PREFIX . $this->properties->baseName();

		if ( $suffix ) {
			$filter .= '.' . $suffix;
		}

		return $filter;
	}

	/**
	 * Returns the package properties.
	 *
	 * @return Properties
	 */
	public function properties(): Properties {
		return $this->properties;
	}

	/**
	 * Returns the compiled PSR-11 container.
	 *
	 * @return ContainerInterface
	 */
	public function container(): ContainerInterface {
		$this->assertStatus( self::STATUS_INITIALIZED, 'obtain the container instance', '>=' );
		$this->hasContainer = true;

		return $this->containerConfigurator->createReadOnlyContainer();
	}

	/**
	 * Returns whether the compiled container has been requested.
	 *
	 * @return bool
	 */
	public function hasContainer(): bool {
		return $this->hasContainer;
	}

	/**
	 * Returns the package base name.
	 *
	 * @return string
	 */
	public function name(): string {
		return $this->properties->baseName();
	}

	/**
	 * Checks whether the package is currently at the given status.
	 *
	 * @param int $status Status constant to check.
	 * @return bool
	 */
	public function statusIs( int $status ): bool {
		return $this->checkStatus( $status );
	}

	/**
	 * Returns whether the package is in a failed status.
	 *
	 * @return bool
	 */
	public function hasFailed(): bool {
		return self::STATUS_FAILED === $this->status;
	}

	/**
	 * Checks whether the package has reached (or passed) the given status.
	 *
	 * @param int $status Status constant to check.
	 * @return bool
	 */
	public function hasReachedStatus( int $status ): bool {
		if ( $this->hasFailed() ) {
			return false;
		}

		return isset( self::SUCCESS_STATUSES[ $status ] ) && $this->checkStatus( $status, '>=' );
	}

	/**
	 * Compares the current status against the given one using an operator.
	 *
	 * @param int    $status   Status constant to compare against.
	 * @param string $operator Comparison operator, one of self::OPERATORS.
	 * @return bool
	 */
	private function checkStatus( int $status, string $operator = '==' ): bool {
		assert( isset( self::OPERATORS[ $operator ] ) );

		return version_compare( (string) $this->status, (string) $status, $operator );
	}

	/**
	 * Moves the package to the given status and fires the mapped hooks.
	 *
	 * @param int $status Status constant to move to.
	 * @return void
	 */
	private function progress( int $status ): void {
		$this->status = $status;

		[$packageHookSuffix, $globalHook] = self::STATUSES_ACTIONS_MAP[ $status ] ?? array( null, null );
		if ( null !== $packageHookSuffix ) {
			do_action( $this->hookName( $packageHookSuffix ), $this );
		}
		if ( null !== $globalHook ) {
			do_action( $globalHook, $this->name(), $this );
		}
	}

	/**
	 * Handles a caught error by firing the failure hook and, in debug, rethrowing.
	 *
	 * @param \Throwable $throwable Caught error.
	 * @param string     $action    Failure action hook suffix.
	 * @return void
	 * @throws \Throwable Rethrown when the package is in debug mode.
	 */
	private function handleFailure( \Throwable $throwable, string $action ): void {
		$this->progress( self::STATUS_FAILED );
		$hook = $this->hookName( $action );
		did_action( $hook ) || do_action( $hook, $throwable );

		if ( $this->properties->isDebug() ) {
			throw $throwable;
		}

		$this->lastError = $throwable;
	}

	/**
	 * Asserts the current status satisfies the given comparison, throwing otherwise.
	 *
	 * @param int    $status   Status constant to compare against.
	 * @param string $action   Human-readable action, used in the exception message.
	 * @param string $operator Comparison operator, one of self::OPERATORS.
	 * @return void
	 * @throws \Exception When the status assertion fails.
	 */
	private function assertStatus( int $status, string $action, string $operator = '==' ): void {
		if ( ! $this->checkStatus( $status, $operator ) ) {
			throw new \Exception(
				sprintf( "Can't %s at this point of application.", esc_html( $action ) ),
				0,
				$this->lastError // phpcs:ignore WordPress.Security.EscapeOutput
			);
		}
	}
}
