<?php

declare(strict_types=1);

namespace ThemeZee\Packable\Tests\Unit;

use Brain\Monkey;
use ThemeZee\Packable\Module\ExecutableModule;
use ThemeZee\Packable\Module\ServiceModule;
use ThemeZee\Packable\Package;
use ThemeZee\Packable\Properties\Properties;
use ThemeZee\Packable\Tests\TestCase;
use Psr\Container\ContainerInterface;

class PackageTest extends TestCase
{
    /**
     * @test
     */
    public function testBasic(): void
    {
        $expectedName = 'foo';
        $propertiesStub = $this->stubProperties($expectedName);

        $package = Package::new($propertiesStub);

        static::assertTrue($package->statusIs(Package::STATUS_IDLE));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_IDLE));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_INITIALIZED));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_BOOTING));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_BOOTED));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_DONE));

        $package->build();

        static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_IDLE));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_INITIALIZED));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_BOOTING));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_BOOTED));
        static::assertFalse($package->hasReachedStatus(Package::STATUS_DONE));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))
            ->once()
            ->whenHappen(static function (Package $package): void {
                static::assertTrue($package->statusIs(Package::STATUS_BOOTED));
            });

        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_IDLE));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_INITIALIZED));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_BOOTING));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_BOOTED));
        static::assertTrue($package->hasReachedStatus(Package::STATUS_DONE));
        static::assertFalse($package->hasReachedStatus(6));

        static::assertSame($expectedName, $package->name());
        static::assertInstanceOf(Properties::class, $package->properties());
        static::assertInstanceOf(ContainerInterface::class, $package->container());
        static::assertEmpty($package->modulesStatus()[Package::MODULES_ALL]);
    }

    /**
     * @test
     * @dataProvider provideHookNameSuffix
     *
     * @param string $suffix
     * @param string $baseName
     * @param string $expectedHookName
     */
    public function testHookName(string $suffix, string $baseName, string $expectedHookName): void
    {
        $propertiesStub = $this->stubProperties($baseName);
        $package = Package::new($propertiesStub);
        static::assertSame($expectedHookName, $package->hookName($suffix));
    }

    /**
     * @return \Generator
     */
    public static function provideHookNameSuffix(): \Generator
    {
        $expectedName = 'baseName';
        $baseHookName = 'themezee.packable.' . $expectedName;
        yield 'no suffix' => [
            '',
            $expectedName,
            $baseHookName,
        ];

        yield 'failed boot' => [
            Package::ACTION_FAILED_BOOT,
            $expectedName,
            $baseHookName . '.' . Package::ACTION_FAILED_BOOT,
        ];

        yield 'init' => [
            Package::ACTION_INIT,
            $expectedName,
            $baseHookName . '.' . Package::ACTION_INIT,
        ];

        yield 'booted' => [
            Package::ACTION_BOOTED,
            $expectedName,
            $baseHookName . '.' . Package::ACTION_BOOTED,
        ];
    }

    /**
     * @test
     */
    public function testBootWithEmptyModule(): void
    {
        $expectedId = 'my-module';

        $moduleStub = $this->stubModule($expectedId);
        $propertiesStub = $this->stubProperties('name', true);

        $package = Package::new($propertiesStub)->addModule($moduleStub);

        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
        static::assertTrue($package->moduleIs($expectedId, Package::MODULE_NOT_ADDED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_ADDED));

        // booting again return false, but we expect no breakage
        static::assertFalse($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
    }

    /**
     * @test
     */
    public function testBuildWithEmptyModule(): void
    {
        $expectedId = 'my-module';

        $moduleStub = $this->stubModule($expectedId);
        $propertiesStub = $this->stubProperties('name', true);

        $package = Package::new($propertiesStub)->addModule($moduleStub);

        $package->build();
        static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
        static::assertTrue($package->moduleIs($expectedId, Package::MODULE_NOT_ADDED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_REGISTERED));
        static::assertFalse($package->moduleIs($expectedId, Package::MODULE_ADDED));

        // building again we expect no breakage
        $package->build()->build();
        static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
    }

    /**
     * @test
     */
    public function testBootWithServiceModule(): void
    {
        $moduleId = 'module_test';
        $serviceId = 'service_test';

        $package = $this->stubSimplePackage('test');

        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->container()->has($serviceId));
    }

    /**
     * @test
     */
    public function testBuildWithServiceModule(): void
    {
        $moduleId = 'module_test';
        $serviceId = 'service_test';

        $package = $this->stubSimplePackage('test');

        $package->build();
        static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_NOT_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_REGISTERED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->container()->has($serviceId));
    }

    /**
     * @test
     */
    public function testBootWithExecutableModule(): void
    {
        $moduleId = 'executable-module';
        $module = $this->stubModule($moduleId, ExecutableModule::class);
        $module->expects('run')->andReturn(true);

        $package = Package::new($this->stubProperties())->addModule($module);

        static::assertTrue($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_DONE));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXECUTED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXECUTION_FAILED));
    }

    /**
     * @test
     */
    public function testBuildWithExecutableModule(): void
    {
        $moduleId = 'executable-module';
        $module = $this->stubModule($moduleId, ExecutableModule::class);
        $module->expects('run')->never();

        $package = Package::new($this->stubProperties())->addModule($module);

        $package->build();
        static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXECUTED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXECUTION_FAILED));
    }

    /**
     * Test, when the ExecutableModule::run() return false, that the state is correctly set.
     *
     * @test
     */
    public function testBootWithExecutableModuleFailed(): void
    {
        $moduleId = 'executable-module';
        $module = $this->stubModule($moduleId, ExecutableModule::class);
        $module->expects('run')->andReturn(false);

        $package = Package::new($this->stubProperties())->addModule($module);

        static::assertTrue($package->boot());
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_ADDED));
        static::assertFalse($package->moduleIs($moduleId, Package::MODULE_EXECUTED));
        static::assertTrue($package->moduleIs($moduleId, Package::MODULE_EXECUTION_FAILED));
    }

    /**
     * @test
     */
    public function testAddModuleFailsAfterBuild(): void
    {
        $package = Package::new($this->stubProperties('test', true))->build();

        $this->expectExceptionMessageMatches("/add module/i");

        $package->addModule($this->stubModule());
    }

    /**
     * @test
     */
    public function testBuildResolveServices(): void
    {
        $module = new class () implements ServiceModule, ExecutableModule
        {
            public function id(): string
            {
                return 'test-module';
            }

            public function services(): array
            {
                return [
                    'dependency' => static function (): object {
                        return (object) ['x' => 'Works!'];
                    },
                    'service' => static function (ContainerInterface $container): object {
                        $works = $container->get('dependency')->x;

                        return new class (['works?' => $works]) extends \ArrayObject
                        {
                            public function works(): string
                            {
                                return (string) $this->offsetGet('works?');
                            }
                        };
                    },
                ];
            }

            public function run(ContainerInterface $container): bool
            {
                throw new \Error('This should not run!');
            }
        };

        $actual = Package::new($this->stubProperties())
            ->addModule($module)
            ->build()
            ->container()
            ->get('service')
            ->works();

        static::assertSame('Works!', $actual);
    }

    /**
     * @test
     */
    public function testBootFireHooks(): void
    {
        $package = $this->stubSimplePackage('1');

        $log = [];

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZING));
                    $log[] = 1;
                }
            );

        Monkey\Actions\expectDone(Package::ACTION_PACKABLE_INIT)
            ->once()
            ->whenHappen(
                static function (string $packageName, Package $package) use (&$log): void {
                    static::assertSame('package_1', $packageName);
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZING));
                    $log[] = 2;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
                    $log[] = 3;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_BOOTED));
                    $log[] = 4;
                }
            );

        $package->boot();

        static::assertSame(range(1, 4), $log);
    }

    /**
     * This is identical to the above where we do only `boot()`, we do here `build()->boot()` but
     * we expect identical result.
     *
     * @test
     */
    public function testBuildAndBootFireHooks(): void
    {
        $package = $this->stubSimplePackage('1');

        $log = [];

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZING));
                    $log[] = 1;
                }
            );

        Monkey\Actions\expectDone(Package::ACTION_PACKABLE_INIT)
            ->once()
            ->whenHappen(
                static function (string $packageName, Package $package) use (&$log): void {
                    static::assertSame('package_1', $packageName);
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZING));
                    $log[] = 2;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
                    $log[] = 3;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_BOOTED));
                    $log[] = 4;
                }
            );

        $package->build()->boot();

        static::assertSame(range(1, 4), $log);
    }

    /**
     * This is mostly identical to the above where we do `build()->boot()` but here we do
     * we do just `build()` and we expect very similar result, but ACTION_BOOTED never fired.
     *
     * @test
     */
    public function testBuildFireHooks(): void
    {
        $package = $this->stubSimplePackage('1');

        $log = [];

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZING));
                    $log[] = 1;
                }
            );

        Monkey\Actions\expectDone(Package::ACTION_PACKABLE_INIT)
            ->once()
            ->whenHappen(
                static function (string $packageName, Package $package) use (&$log): void {
                    static::assertSame('package_1', $packageName);
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZING));
                    $log[] = 2;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))
            ->once()
            ->whenHappen(
                static function (Package $package) use (&$log): void {
                    static::assertTrue($package->statusIs(Package::STATUS_INITIALIZED));
                    $log[] = 3;
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))
            ->never();

        $package->build();

        static::assertSame(range(1, 3), $log);
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBootFromInitHookDebugOff(): void
    {
        $package = Package::new($this->stubProperties('test', false));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->whenHappen([$package, 'boot']);

        Monkey\Actions\expectDone(Package::ACTION_PACKABLE_INIT)->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->never();

        $package->build();
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBootFromInitHookDebugOn(): void
    {
        $package = Package::new($this->stubProperties('test', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->whenHappen([$package, 'boot']);

        Monkey\Actions\expectDone(Package::ACTION_PACKABLE_INIT)->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->never();

        $this->expectExceptionMessageMatches('/boot/i');
        $package->build();
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBootFromInitializedHook(): void
    {
        $package = Package::new($this->stubProperties('test', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))
            ->once()
            ->whenHappen([$package, 'boot']);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))->once();
        Monkey\Actions\expectDone(Package::ACTION_PACKABLE_INIT)->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->never();

        $this->expectExceptionMessageMatches('/boot/i');
        $package->build();
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBootFromReadyHook(): void
    {
        $package = Package::new($this->stubProperties('test', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))
            ->once()
            ->whenHappen([$package, 'boot']);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))->once();
        Monkey\Actions\expectDone(Package::ACTION_PACKABLE_INIT)->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->once();

        $this->expectExceptionMessageMatches('/boot/i');
        $package->boot();
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBuildFromInitHook(): void
    {
        $package = Package::new($this->stubProperties('test', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->whenHappen([$package, 'build']);

        Monkey\Actions\expectDone(Package::ACTION_PACKABLE_INIT)->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->never();

        $this->expectExceptionMessageMatches('/build/i');
        $package->build();
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBuildFromInitializedHook(): void
    {
        $package = Package::new($this->stubProperties('test', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))
            ->once()
            ->whenHappen([$package, 'build']);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))->once();
        Monkey\Actions\expectDone(Package::ACTION_PACKABLE_INIT)->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))->never();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->never();

        $this->expectExceptionMessageMatches('/build/i');
        $package->build();
    }

    /**
     * @test
     */
    public function testItFailsWhenCallingBuildFromReadyHook(): void
    {
        $package = Package::new($this->stubProperties('test', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_BOOTED))
            ->once()
            ->whenHappen([$package, 'build']);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))->once();
        Monkey\Actions\expectDone(Package::ACTION_PACKABLE_INIT)->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INITIALIZED))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))->once();
        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->once();

        $this->expectExceptionMessageMatches('/build/i');
        $package->boot();
    }

    /**
     * @test
     */
    public function testPropertiesCanBeRetrievedFromContainer(): void
    {
        $expected = $this->stubProperties();
        $actual = Package::new($expected)->build()->container()->get(Package::PROPERTIES);

        static::assertSame($expected, $actual);
    }

    /**
     * Test, when multiple modules are added, and debug is true, 'modules' status is set correctly.
     *
     * @test
     */
    public function testStatusForMultipleModulesWhenDebug(): void
    {
        $emptyModule = $this->stubModule('empty');
        $emptyServicesModule = $this->stubModule('empty_services', ServiceModule::class);

        $servicesModule = $this->stubModule('service', ServiceModule::class);
        $servicesModule->expects('services')->andReturn($this->stubServices('S1', 'S2'));

        $multiModule = $this->stubModule(
            'multi',
            ServiceModule::class
        );
        $multiModule->expects('services')->andReturn($this->stubServices('MS1'));

        $package = Package::new($this->stubProperties('name', true))
            ->addModule($emptyModule)
            ->addModule($emptyServicesModule)
            ->addModule($servicesModule)
            ->addModule($multiModule);

        static::assertTrue($package->build()->boot());

        $expectedStatus = [
            Package::MODULES_ALL => [
                'empty not-added',
                'empty_services not-added',
                'service registered (S1, S2)',
                'service added',
                'multi registered (MS1)',
                'multi added',
            ],
            Package::MODULE_NOT_ADDED => [
                'empty',
                'empty_services',
            ],
            Package::MODULE_REGISTERED => [
                'service',
                'multi',
            ],
            Package::MODULE_ADDED => [
                'service',
                'multi',
            ],
        ];

        $actualStatus = $package->modulesStatus();

        ksort($expectedStatus, SORT_STRING);
        ksort($actualStatus, SORT_STRING);

        static::assertSame($expectedStatus, $actualStatus);
    }

    /**
     * Test, when multiple modules are added, and debug is false, 'modules' status is set correctly.
     *
     * @test
     */
    public function testStatusForMultipleModulesWhenNotDebug(): void
    {
        $emptyModule = $this->stubModule('empty');
        $emptyServicesModule = $this->stubModule('empty_services', ServiceModule::class);

        $servicesModule = $this->stubModule('service', ServiceModule::class);
        $servicesModule->expects('services')->andReturn($this->stubServices('S1', 'S2'));

        $multiModule = $this->stubModule(
            'multi',
            ServiceModule::class
        );
        $multiModule->expects('services')->andReturn($this->stubServices('MS1'));

        $package = Package::new($this->stubProperties('name', false))
            ->addModule($emptyModule)
            ->addModule($emptyServicesModule)
            ->addModule($servicesModule)
            ->addModule($multiModule);

        static::assertTrue($package->build()->boot());

        $expectedStatus = [
            Package::MODULES_ALL => [
                'empty ' . Package::MODULE_NOT_ADDED,
                'empty_services ' . Package::MODULE_NOT_ADDED,
                'service ' . Package::MODULE_REGISTERED,
                'service ' . Package::MODULE_ADDED,
                'multi ' . Package::MODULE_REGISTERED,
                'multi ' . Package::MODULE_ADDED,
            ],
            Package::MODULE_NOT_ADDED => [
                'empty',
                'empty_services',
            ],
            Package::MODULE_REGISTERED => [
                'service',
                'multi',
            ],
            Package::MODULE_ADDED => [
                'service',
                'multi',
            ],
        ];

        $actualStatus = $package->modulesStatus();

        ksort($expectedStatus, SORT_STRING);
        ksort($actualStatus, SORT_STRING);

        static::assertSame($expectedStatus, $actualStatus);
    }

    /**
     * When an exception happen inside `Package::boot()` and debug is off, we expect the exception
     * to be caught, an "boot failed" action to be failed, and the Package to be in errored status.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnBootDebugModeOff(): void
    {
        $exception = new \Exception('Test');

        $module = $this->stubModule('id', ExecutableModule::class);
        $module->expects('run')->andThrow($exception);

        $package = Package::new($this->stubProperties())->addModule($module);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->with($exception);

        static::assertFalse($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
        static::assertTrue($package->hasFailed());
        static::assertFalse($package->hasReachedStatus(Package::STATUS_IDLE));
    }

    /**
     * When an exception happen inside `Package::boot()` and debug is of, we expect it to bubble up.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnBootDebugModeOn(): void
    {
        $exception = new \Exception('Test');

        $module = $this->stubModule('id', ExecutableModule::class);
        $module->expects('run')->andThrow($exception);

        $package = Package::new($this->stubProperties('basename', true))->addModule($module);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->with($exception);

        $this->expectExceptionObject($exception);
        $package->boot();
    }

    /**
     * When multiple calls to `Package::addPackage()` throw an exception, and debug is off, we
     * expect none of them to bubble up, and the first to cause the "build failed" action.
     * We also expect the Package to be in errored status.
     * We expect all other `Package::addPackage()` exceptions to do not fire action hook.
     * We expect Package::build()` to fail without doing anything. Finally, when `Package::boot()`
     * is called, we expect the action "boot failed" to be called, and the passed exception to have
     * an exception hierarchy with all the thrown exceptions.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnAddModuleDebugModeOff(): void
    {
        $exception = new \Exception('Test 1');

        $module1 = $this->stubModule('one', ServiceModule::class);
        $module1->expects('services')->andThrow($exception);

        $module2 = $this->stubModule('two', ServiceModule::class);
        $module2->expects('services')->never();

        $package = Package::new($this->stubProperties());

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_ADD_MODULE))
            ->once()
            ->whenHappen(
                static function (\Throwable $throwable) use ($exception, $package): void {
                    static::assertSame($exception, $throwable);
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable) use ($package): void {
                    $this->assertThrowableMessageMatches($throwable, 'build package');
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable) use ($exception, $package): void {
                    $this->assertThrowableMessageMatches($throwable, 'boot application');
                    $previous = $throwable->getPrevious();
                    static::assertTrue($previous instanceof \Throwable);
                    $this->assertThrowableMessageMatches($previous, 'build package');
                    $previous = $previous->getPrevious();
                    static::assertTrue($previous instanceof \Throwable);
                    $this->assertThrowableMessageMatches($previous, 'add module');
                    static::assertSame($exception, $previous->getPrevious());
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        static::assertFalse($package->addModule($module1)->addModule($module2)->build()->boot());
        static::assertTrue($package->hasFailed());
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
    }

    /**
     * The same as the test above, but this time we call `Package::boot()` directly, instead of
     * `$package->build()->boot()`, but the expectations are identical.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnAddModuleWithoutBuildDebugModeOff(): void
    {
        $exception = new \Exception('Test 1');

        $module1 = $this->stubModule('one', ServiceModule::class);
        $module1->expects('services')->andThrow($exception);

        $module2 = $this->stubModule('two', ServiceModule::class);
        $module2->expects('services')->never();

        $package = Package::new($this->stubProperties());

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_ADD_MODULE))
            ->once()
            ->whenHappen(
                static function (\Throwable $throwable) use ($exception, $package): void {
                    static::assertSame($exception, $throwable);
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable) use ($exception, $package): void {
                    $this->assertThrowableMessageMatches($throwable, 'boot application');
                    $previous = $throwable->getPrevious();
                    static::assertTrue($previous instanceof \Throwable);
                    $this->assertThrowableMessageMatches($previous, 'two');
                    static::assertSame($exception, $previous->getPrevious());
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        $package = $package->addModule($module1)->addModule($module2);

        static::assertFalse($package->boot());
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
        static::assertTrue($package->hasFailed());
    }

    /**
     * When `Package::build()` throws an exception, and debug is off, we expect it to be caught, the
     * "build failed" action to be fired, and the Package to be in errored status. When after that
     * `Package::boot()` is called we expect the action "boot failed" to be called passing an
     * exception whose "previous" is the exception thrown by `Package::build()`.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnBuildDebugModeOff(): void
    {
        $exception = new \Exception('Test');

        $package = Package::new($this->stubProperties());

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->andThrow($exception);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                static function (\Throwable $throwable) use ($exception, $package): void {
                    static::assertSame($exception, $throwable);
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))
            ->once()
            ->whenHappen(
                function (\Throwable $throwable) use ($exception, $package): void {
                    $this->assertThrowableMessageMatches($throwable, 'boot application');
                    static::assertSame($exception, $throwable->getPrevious());
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                }
            );

        static::assertFalse($package->build()->boot());
        static::assertTrue($package->statusIs(Package::STATUS_FAILED));
        static::assertTrue($package->hasFailed());
    }

    /**
     * When `Package::build()` throws an exception, and debug is on, we expect it to bubble up.
     *
     * @test
     */
    public function testFailureFlowWithFailureOnBuildDebugModeOn(): void
    {
        $exception = new \Exception('Test');

        $package = Package::new($this->stubProperties('basename', true));

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_INIT))
            ->once()
            ->andThrow($exception);

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BUILD))
            ->once()
            ->whenHappen(
                static function (\Throwable $throwable) use ($exception, $package): void {
                    static::assertSame($exception, $throwable);
                    static::assertTrue($package->statusIs(Package::STATUS_FAILED));
                    static::assertTrue($package->hasFailed());
                }
            );

        Monkey\Actions\expectDone($package->hookName(Package::ACTION_FAILED_BOOT))->never();

        $this->expectExceptionObject($exception);
        $package->build()->boot();
    }
}
