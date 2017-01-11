Composer Janitor Plugin
=======================

Cleanup Composer packages to remove unneeded files and folders if you intend to keep you project dependencies in version control. The plugin removes files that belong to 4 default rule groups ([docs](https://github.com/hardpixel/composer-janitor/blob/master/src/Plugin.php#L213), [tests](https://github.com/hardpixel/composer-janitor/blob/master/src/Plugin.php#L242), [system](https://github.com/hardpixel/composer-janitor/blob/master/src/Plugin.php#L266), [wp](https://github.com/hardpixel/composer-janitor/blob/master/src/Plugin.php#L279)) from all the project dependencies when you run `composer install` or `composer-update`. You can configure extra rule groups or disable default groups by adding options to the composer.json file.


### Configure

You can configure Composer Janitor Plugin by adding a `cleanup` key in your composer.json.

`disable`  : Disable default rule groups  
`rules`    : Define custom rule groups  
`packages` : Define rules to specified packages

Below you can see an example configuration.

    "config": {
      "cleanup": {
        "disable": ["system"],
        "rules": {
          "custom": [
            ".git*",
            ".idea",
            ".htaccess",
            ".editorconfig",
            ".phpstorm.meta.php",
            ".php_cs",
            "*.iml",
            "composer.lock",
            "bower*"
          ]
        },
        "packages": {
          "masterminds/html5": ["sami.php", "bin"],
          "querypath/querypath": ["patches", "bin", "phar"],
          "mustache/mustache": "vendor",
          "pelago/emogrifier": "Configuration",
          "wpackagist-plugin/piklist": "add-ons",
          "wpackagist-plugin/polylang": "lingotek"
        }
      }
    }
