# ICS Feed for Modern Events Calendar

This WordPress plugin generates a ICS feed from events in the [Modern Events Calendar](https://webnus.net/modern-events-calendar).

## Installation & Updating

Install the plugin by downloading the `mec-ics-<version>.zip` from the [latest release](https://github.com/lmr-hh/wordpress-mec-ics/releases/latest) and install the plugin manually.

The plugin can be updated to future GitHub releases using the integrated WordPress update. This is implemented via the [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker).

## Usage

After activating the plugin you will see a settings page `M.E. Calendar > ICS Feed` where you can setup the ICS feed. The only option that must be configured is the `Feed Slug`. After the slug is set the plugin will show you the URL of the feed (usually something like `http://example.com/feed/myicsfeed`).

You can then subscribe to the ICS in the calendar program of your choice by adding the feed URL. Additionally you can filter events by `categories`, `tags`, `labels`, `locations`, and `organizers` using GET parameters. For example the URL `http://example.com/feed/myicsfeed?tags=hello,world&categories=demo` will contain a feed of events that belong to the category `demo` AND have one or both of the tags `hello` and `world` associated with them. The other filters work analogously. Multiple filter values are ORed, different filters are ANDed. All filters use the slugs of their respective taxonomies.

## Unsupported Features

- It is not possible to configure multiple ICS feeds.
- Recurring events are not currently supported by the plugin.

## Development

Contributions are always welcome. This project mainly uses PHP Composer as its dependency manager and build system. Here are some relevant commands to get started (a working [Composer](https://getcomposer.org) install is required):

- `composer install` will install all dependencies and development dependencies. The development dependencies include a version of WordPress Core, mainly for better autocompletion and static analysis support.
- `composer run-script extract-strings` extracts localizable strings from the `includes` directory and updates the `*.po` files in the `languages` folder.
- `composer run-script compile-languages` compiles the `*.po` files to `*.mo` files. You need to run this command if you want to see translations locally. Do not commit the `*.mo` files to the repository as they are automatically built for each release.
- `composer lint` runs [PHP_CodeSniffer](https://github.com/squizlabs/PHP_CodeSniffer) and lints the coding style.
