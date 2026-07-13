# Modules
Services can be _registered_ and _booted_ via a so-called Module in your Application.

Those Modules can be registered to your Application via the provided `ServiceModule`- and `ExecutableModule`-interfaces.

**Default Modules** are registered before `Package::boot()`:

```php
<?php
ThemeZee\Packable\Package::new($properties)
    ->addModule(new ModuleWhichProvidesServices())
    ->addModule(new ModuleWhichIsExecuted())
    ->boot();
```

Each Module implementation will extend the basic `Module`-interface which is required to define a `Module::id(): string`. This identifier will be re-used in Package-class to keep track of the current state of your Module and will allow easier debugging of your Application. To avoid defining this by hand, it is possible to use the following Trait: `ThemeZee\Packable\Module\ModuleClassNameIdTrait`

## ServiceModule
A ServiceModule will allow you to register new Services to the Container, to access them later on a specific point. The `ServiceModule::services(): array` will return an array of Services. Each array-key is an identifier for your Service, while the array-value will contain a callable which receives the primary Container (read-only) to set up your Service.

Services registered via `ServiceModule::services()` will only be resolved once and on continues access the same instance will be returned.

```php
<?php

declare(strict_types=1);

use ThemeZee\Packable\Module\ServiceModule;
use ThemeZee\Packable\Module\ModuleClassNameIdTrait;
use Psr\Container\ContainerInterface;

class ModuleWhichProvidesServices implements ServiceModule
{
    use ModuleClassNameIdTrait;

    public function services() : array
    {
        return [
            ServiceOne::class => static function(ContainerInterface $container): ServiceOne {
                return new ServiceOne();
            } 
        ];
    }
}
```

## ExecutableModule
If there is functionality that needs to be executed, you can make the Module executable like following:

```php
<?php

declare(strict_types=1);

use ThemeZee\Packable\Module\ExecutableModule;
use ThemeZee\Packable\Module\ModuleClassNameIdTrait;
use Psr\Container\ContainerInterface;

class ModuleWhichIsExecuted implements ExecutableModule
{
    use ModuleClassNameIdTrait;

    public function run(ContainerInterface $container) : bool
    {
        $serviceOne = $container->get(ServiceOne::class);
        add_action('init', $serviceOne);

        return true;
    }
}
```

The return value true/false will determine if the Module has successfully been executed or not.

### Context-based execution of Services
To execute Services based on a Context like “Rest Request” or “FrontOffice” we recommend the usage of [inpsyde/wp-context](https://github.com/inpsyde/wp-context). This package allows you to access the current request context and based on that you can execute your Services:

```php
<?php

declare(strict_types=1);

use ThemeZee\Packable\Module\ExecutableModule;
use ThemeZee\Packable\Module\ModuleClassNameIdTrait;
use Psr\Container\ContainerInterface;
use ThemeZee\WpContext;

class ModuleFour implements ExecutableModule
{
    use ModuleClassNameIdTrait;

    public function run(ContainerInterface $container) : bool
    {
        $context = WpContext::determine();
        if (!$context->is(WpContext::AJAX, WpContext::CRON)) {
          return false;
        }

        // stuff for requests that are either AJAX or WP cron
        $serviceOne = $container->get(ServiceOne::class);
        add_action('init', $serviceOne);

        return true;
    }
}
```

### Service overrides
When the same Service id is registered more than once by multiple modules, the latter will override the former.

```php
<?php

declare(strict_types=1);

use ThemeZee\Packable\Module\ServiceModule;
use ThemeZee\Packable\Module\ModuleClassNameIdTrait;
use Psr\Container\ContainerInterface;

class ModuleWhichProvidesServices implements ServiceModule
{
    use ModuleClassNameIdTrait;

    public function services() : array
    {
        return [
            ServiceOne::class => static function(ContainerInterface $container): ServiceOne {
                return new ServiceOne();
            } 
        ];
    }
}

class ModuleWhichOverridesServices implements ServiceModule
{
    use ModuleClassNameIdTrait;

    public function services() : array
    {
        return [
            ServiceOne::class => static function(ContainerInterface $container): ServiceOne {
                return new class extends ServiceOne{
                    /*  */
                };
            } 
        ];
    }
}
```

*For module developers* this opens up some possibilities, like the ability to inject Mocks in the container, or to
replace a service with a different implementation without an unneeded and/or wasteful constructor call of the
now-obsolete original.

*For package maintainers* this is something to watch out for when consuming Modules from multiple sources. 
However unlikely it may be, there is a risk of _unintentional_ overrides resulting in unexpected behaviour.
