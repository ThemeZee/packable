<?php

declare(strict_types=1);

namespace ThemeZee\Packable;

use ThemeZee\Packable\Container\ContainerConfigurator;
use ThemeZee\Packable\Module\ExecutableModule;
use ThemeZee\Packable\Module\Module;
use ThemeZee\Packable\Module\ServiceModule;
use ThemeZee\Packable\Properties\Properties;
use Psr\Container\ContainerInterface;

/**
 * @phpstan-import-type Service from \ThemeZee\Packable\Module\ServiceModule
 */
class Package
{
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
    public const MODULE_ADDED = 'added';
    public const MODULE_NOT_ADDED = 'not-added';
    public const MODULE_REGISTERED = 'registered';
    public const MODULE_EXECUTED = 'executed';
    public const MODULE_EXECUTION_FAILED = 'executed-failed';
    public const MODULES_ALL = '*';

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
    public const STATUS_IDLE = 2;
    public const STATUS_INITIALIZING = 3;
    public const STATUS_INITIALIZED = 4;
    public const STATUS_BOOTING = 5;
    public const STATUS_BOOTED = 7;
    public const STATUS_DONE = 8;
    public const STATUS_FAILED = -8;

    // Map of status to package-specific and global hook, both optional (i..e, null).
    private const STATUSES_ACTIONS_MAP = [
        self::STATUS_INITIALIZING => [self::ACTION_INIT, self::ACTION_PACKABLE_INIT],
        self::STATUS_INITIALIZED => [self::ACTION_INITIALIZED, null],
        self::STATUS_BOOTED => [self::ACTION_BOOTED, null],
    ];

    private const SUCCESS_STATUSES = [
        self::STATUS_IDLE => self::STATUS_IDLE,
        self::STATUS_INITIALIZING => self::STATUS_INITIALIZING,
        self::STATUS_INITIALIZED => self::STATUS_INITIALIZED,
        self::STATUS_BOOTING => self::STATUS_BOOTING,
        self::STATUS_BOOTED => self::STATUS_BOOTED,
        self::STATUS_DONE => self::STATUS_DONE,
    ];

    private const OPERATORS = [
        '<' => '<',
        '<=' => '<=',
        '>' => '>',
        '>=' => '>=',
        '==' => '==',
        '!=' => '!=',
    ];

    /** @var Package::STATUS_* */
    private int $status = self::STATUS_IDLE;
    /** @var array<string, list<string>> */
    private array $moduleStatus = [self::MODULES_ALL => []];
    /** @var list<ExecutableModule> */
    private array $executables = [];
    private Properties $properties;
    private ContainerConfigurator $containerConfigurator;
    private bool $built = false;
    private bool $hasContainer = false;
    private ?\Throwable $lastError = null;

    /**
     * @param Properties $properties
     * @param ContainerInterface ...$containers
     * @return Package
     */
    public static function new(Properties $properties, ContainerInterface ...$containers): Package
    {
        return new self($properties, ...$containers);
    }

    /**
     * @param Properties $properties
     * @param ContainerInterface ...$containers
     */
    private function __construct(Properties $properties, ContainerInterface ...$containers)
    {
        $this->properties = $properties;

        $this->containerConfigurator = new ContainerConfigurator($containers);
        $this->containerConfigurator->addService(
            self::PROPERTIES,
            static function () use ($properties): Properties {
                return $properties;
            }
        );
    }

    /**
     * @param Module $module
     * @return static
     */
    public function addModule(Module $module): Package
    {
        try {
            $reason = sprintf('add module %s', $module->id());
            $this->assertStatus(self::STATUS_FAILED, $reason, '!=');
            $this->assertStatus(self::STATUS_INITIALIZING, $reason, '<=');

            $registeredServices = $this->addModuleServices($module);
            $isExecutable = $module instanceof ExecutableModule;

            // ExecutableModules are collected and executed on Package::boot()
            // when the Container is being compiled.
            if ($isExecutable) {
                /** @var ExecutableModule $module */
                $this->executables[] = $module;
            }

            $added = $registeredServices || $isExecutable;
            $status = $added ? self::MODULE_ADDED : self::MODULE_NOT_ADDED;
            $this->moduleProgress($module->id(), $status);
        } catch (\Throwable $throwable) {
            $this->handleFailure($throwable, self::ACTION_FAILED_ADD_MODULE);
        }

        return $this;
    }

    /**
     * @return static
     */
    public function build(): Package
    {
        try {
            // Be tolerant about things like `$package->build()->build()`.
            // Sometimes, from the extern, we might want to call `build()` to ensure the container
            // is ready before accessing a service. And in that case we don't want to throw an
            // exception if the container is already built.
            if ($this->built && $this->statusIs(self::STATUS_INITIALIZED)) {
                return $this;
            }

            // We expect `build` to be called only after `addModule()` which does not change the
            // status, so we expect status to be still "IDLE".
            // This will prevent invalid things like calling `build()` from inside something
            // hooking ACTION_INIT OR ACTION_INITIALIZED.
            $this->assertStatus(self::STATUS_IDLE, 'build package');

            // This will change the status to "INITIALIZING" then fire the action that allow other
            // code to add modules.
            $this->progress(self::STATUS_INITIALIZING);

            // This will change the status to "INITIALIZED" then fire an action when it is safe to
            // access the container, because from this moment on, container is locked from change.
            $this->progress(self::STATUS_INITIALIZED);
        } catch (\Throwable $throwable) {
            $this->handleFailure($throwable, self::ACTION_FAILED_BUILD);
        } finally {
            $this->built = true;
        }

        return $this;
    }

    /**
     * @return bool
     */
    public function boot(): bool
    {
        try {
            // When package is done, nothing should happen to it calling boot again, but we call
            // false to signal something is off.
            if ($this->statusIs(self::STATUS_DONE)) {
                return false;
            }

            // Call build() if not called yet.
            $this->doBuild();

            // Make sure we call boot() on a non-failed instance, and also make a sanity check
            // on the status flow, e.g. prevent calling boot() from an action hook.
            $this->assertStatus(self::STATUS_INITIALIZED, 'boot application');

            // This will change status to STATUS_BOOTING "locking" subsequent call to `boot()`, but
            // no hook is fired here, because at this point we can not do anything more or less than
            // what can be done on the ACTION_INITIALIZED hook, so that hook is sufficient.
            $this->progress(self::STATUS_BOOTING);

            $this->doExecute();

            // This will change status to STATUS_BOOTED and then fire an action that make it
            // possible to hook on a package that has finished its bootstrapping process, so all its
            // "executable" modules have been executed.
            $this->progress(self::STATUS_BOOTED);
        } catch (\Throwable $throwable) {
            $this->handleFailure($throwable, self::ACTION_FAILED_BOOT);

            return false;
        }

        // This will change the status to DONE and will not fire any action.
        // This is a status that proves that everything went well, not only the Package itself,
        // but also anything hooking Package's hooks.
        // The only way to move out of this status is a failure that might only happen directly
        // calling `addModule()` or `build()`.
        $this->progress(self::STATUS_DONE);

        return true;
    }

    /**
     * @return void
     */
    private function doBuild(): void
    {
        // We expect `boot()` to be called either:
        //   1. Directly after `addModule()`, without any `build()` call in between, so
        //     status is IDLE and `$this->built` is `false`. In that case we call `build()` here.
        //   2. After `build()` was already called, so status is INITIALIZED and `$this->built` is
        //     `true`. In that case there is nothing left to do.
        // Any other usage is not allowed (e.g. calling `boot()` from an hook callback). We do
        // nothing and hand control back to `boot()`, which will throw via `assertStatus()`.
        if (!$this->built && $this->statusIs(self::STATUS_IDLE)) {
            $this->build();
        }
    }

    /**
     * @param Module $module
     * @return bool
     */
    private function addModuleServices(Module $module): bool
    {
        /** @var null|array<string, Service> $services */
        $services = $module instanceof ServiceModule ? $module->services() : null;

        if (($services === null) || ($services === [])) {
            return false;
        }

        $ids = [];
        foreach ($services as $id => $service) {
            $this->containerConfigurator->addService($id, $service);
            $ids[] = $id;
        }

        $this->moduleProgress($module->id(), self::MODULE_REGISTERED, $ids);

        return true;
    }

    /**
     * @return void
     */
    private function doExecute(): void
    {
        foreach ($this->executables as $executable) {
            $success = $executable->run($this->container());
            $this->moduleProgress(
                $executable->id(),
                $success ? self::MODULE_EXECUTED : self::MODULE_EXECUTION_FAILED,
            );
        }
    }

    /**
     * @param string $moduleId
     * @param string $status
     * @param list<string>|null $serviceIds
     * @return void
     */
    private function moduleProgress(
        string $moduleId,
        string $status,
        ?array $serviceIds = null
    ): void {

        if (!isset($this->moduleStatus[$status])) {
            $this->moduleStatus[$status] = [];
        }
        $this->moduleStatus[$status][] = $moduleId;

        if (($serviceIds === null) || ($serviceIds === []) || !$this->properties->isDebug()) {
            $this->moduleStatus[self::MODULES_ALL][] = "{$moduleId} {$status}";

            return;
        }

        $description = sprintf('%s %s (%s)', $moduleId, $status, implode(', ', $serviceIds));
        $this->moduleStatus[self::MODULES_ALL][] = $description;
    }

    /**
     * @return array<string, list<string>>
     */
    public function modulesStatus(): array
    {
        return $this->moduleStatus;
    }

    /**
     * @param string $moduleId
     * @param string $status
     *
     * @return bool
     */
    public function moduleIs(string $moduleId, string $status): bool
    {
        return in_array($moduleId, $this->moduleStatus[$status] ?? [], true);
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
     * @param string $suffix
     * @return string
     *
     * @see Package::name()
     */
    public function hookName(string $suffix = ''): string
    {
        $filter = self::HOOK_PREFIX . $this->properties->baseName();

        if ($suffix) {
            $filter .= '.' . $suffix;
        }

        return $filter;
    }

    /**
     * @return Properties
     */
    public function properties(): Properties
    {
        return $this->properties;
    }

    /**
     * @return ContainerInterface
     */
    public function container(): ContainerInterface
    {
        $this->assertStatus(self::STATUS_INITIALIZED, 'obtain the container instance', '>=');
        $this->hasContainer = true;

        return $this->containerConfigurator->createReadOnlyContainer();
    }

    /**
     * @return bool
     */
    public function hasContainer(): bool
    {
        return $this->hasContainer;
    }

    /**
     * @return string
     */
    public function name(): string
    {
        return $this->properties->baseName();
    }

    /**
     * @param int $status
     * @return bool
     */
    public function statusIs(int $status): bool
    {
        return $this->checkStatus($status);
    }

    /**
     * @return bool
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * @param int $status
     * @return bool
     */
    public function hasReachedStatus(int $status): bool
    {
        if ($this->hasFailed()) {
            return false;
        }

        return isset(self::SUCCESS_STATUSES[$status]) && $this->checkStatus($status, '>=');
    }

    /**
     * @param int $status
     * @param value-of<Package::OPERATORS> $operator
     * @return bool
     */
    private function checkStatus(int $status, string $operator = '=='): bool
    {
        assert(isset(self::OPERATORS[$operator]));

        return version_compare((string) $this->status, (string) $status, $operator);
    }

    /**
     * @param Package::STATUS_* $status
     */
    private function progress(int $status): void
    {
        $this->status = $status;

        [$packageHookSuffix, $globalHook] = self::STATUSES_ACTIONS_MAP[$status] ?? [null, null];
        if ($packageHookSuffix !== null) {
            do_action($this->hookName($packageHookSuffix), $this);
        }
        if ($globalHook !== null) {
            do_action($globalHook, $this->name(), $this);
        }
    }

    /**
     * @param \Throwable $throwable
     * @param Package::ACTION_FAILED_* $action
     * @return void
     */
    private function handleFailure(\Throwable $throwable, string $action): void
    {
        $this->progress(self::STATUS_FAILED);
        $hook = $this->hookName($action);
        did_action($hook) or do_action($hook, $throwable);

        if ($this->properties->isDebug()) {
            throw $throwable;
        }

        $this->lastError = $throwable;
    }

    /**
     * @param int $status
     * @param string $action
     * @param value-of<Package::OPERATORS> $operator
     */
    private function assertStatus(int $status, string $action, string $operator = '=='): void
    {
        if (!$this->checkStatus($status, $operator)) {
            throw new \Exception(
                sprintf("Can't %s at this point of application.", esc_html($action)),
                0,
                $this->lastError // phpcs:ignore WordPress.Security.EscapeOutput
            );
        }
    }

}
