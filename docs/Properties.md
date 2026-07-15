# Properties
Properties containing additional information about your Application and can be built based on Themes or Plugins. The Properties itself are immutable and only grant access to values after they were injected into the Package-class.

Properties are added to the Package-class and automatically added as a Service to the primary Container.

To access Properties in your application via Container you can use the class constant `Package::PROPERTIES`:

```php
<?php

declare(strict_types=1);

use ThemeZee\Packable\Package;
use ThemeZee\Packable\Properties\Properties;
use ThemeZee\Packable\Module\ExecutableModule;
use ThemeZee\Packable\Module\ModuleClassNameIdTrait;
use Psr\Container\ContainerInterface;

class ModuleThree implements ExecutableModule
{
    use ModuleClassNameIdTrait;

    public function run(ContainerInterface $container) : bool
    {
        /** @var Properties $properties */
        $properties = $container->get(Package::PROPERTIES);
        
        return true;
    }
}
```

A specific instance of your Properties will use the following data:

| Properties method | Theme - style.css | Plugin - file header |
| --- | --- | --- |
| Properties::author() | Author | Author |
| Properties::authorUri() | Author URI | Author URI |
| Properties::description() | Description | Description |
| Properties::domainPath() | Domain Path | Domain Path |
| Properties::name() | Theme Name | Plugin Name |
| Properties::textDomain() | Text Domain | Text Domain |
| Properties::uri() | Theme URI | Plugin URI |
| Properties::version() | Version | Version |
| Properties::requiresWp() | Requires at least | Requires at least |
| Properties::requiresPhp() | Requires PHP | Requires PHP |
| Properties::baseUrl() | WP_Theme::get_stylesheet_directory_uri() | plugins_url() |
| Properties::network() |  | Network |
| Properties::status() | Status |  |
| Properties::tags() | Tags |  |
| Properties::template() | Template |  |



## PluginProperties

Inside your Plugin you can use the following code to automatically generate Properties based on the [Plugins Header](https://developer.wordpress.org/reference/functions/get_plugin_data/):

```php
<?php
use ThemeZee\Packable\Properties;

$properties = Properties\PluginProperties::new('/path/to/plugin-main-file.php');
```

Additionally, PluginProperties will have the following public API:

- `PluginProperties::pluginMainFile(): string` - returns the Plugin main file.
- `PluginProperties::network(): bool` - returns if the Plugin is only network-wide usable.
- `PluginProperties::isActive(): bool` - returns if the current Plugin is active.
- `PluginProperties::isNetworkActive(): bool` - returns if the current Plugin is network-wide active.
- `PluginProperties::isMuPlugin(): bool` - returns if the current Plugin is a must-use Plugin.

Please note that our usage of `get_plugin_data` opts out of translations and HTML-safe text processing (via `wptexturize`) offered by default.
These functions should not be used before the 'init' hook which may be too late for some applications.

## ThemeProperties

To generate Properties for your Theme you need to provide the Theme directory or Theme name. Properties will be built based on the headers in style.css of your Theme:

```php
<?php
use ThemeZee\Packable\Properties;

$properties = Properties\ThemeProperties::new('/path/to/theme/directory/');
```

Additionally, ThemeProperties will have the following public API:

- `ThemeProperties::status(): string` - If the current Theme is “published”.
- `ThemeProperties::tags(): array` - Tags defined in style.css.
- `ThemeProperties::template(): string`
- `ThemeProperties::isChildTheme(): bool` - True, when the Theme is a Child Theme and using a template.
- `ThemeProperties::isCurrentTheme(): bool` - returns true when this Theme is activated.
- `ThemeProperties::parentThemeProperties(): ?ThemeProperties` - returns Properties of the parent theme if it is a child-Theme.
