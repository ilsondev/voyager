# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this is

`ilsondev/voyager` is a Laravel admin panel / BREAD (Browse, Read, Edit, Add, Delete) package — an actively maintained fork of the archived DevDojo Voyager, kept current with modern Laravel and PHP. It's a Composer package (`src/` is the shipped code, autoloaded under `TCG\Voyager\`), not a standalone app — it's installed into a host Laravel application via `composer require` + `php artisan voyager:install`.

Requires PHP `^8.3` and `illuminate/support ^13.0` (Laravel 13+). Older Laravel/PHP versions are not supported by current releases — see CHANGELOG.md for the version where each floor was raised.

## Commands

### PHP / tests

```
composer install
./vendor/bin/phpunit                          # full suite
./vendor/bin/phpunit --no-coverage             # faster, matches CI's main test job
./vendor/bin/phpunit tests/PostTest.php        # single file
./vendor/bin/phpunit --filter testMethodName   # single test method
```

Tests run on Orchestra Testbench (`tests/TestCase.php` extends `Orchestra\Testbench\TestCase`), against an in-memory SQLite DB. Each test boots `VoyagerServiceProvider` and runs `voyager:install --with-dummy` in `setUp()`, so most tests exercise the package as if freshly installed with dummy data. Test discovery is whole-`tests/`-directory (`phpunit.xml`), mixing `Feature/`, `Unit/`, and flat integration tests (`LoginTest.php`, `MenuTest.php`, `RolesTest.php`, etc.) at the top level.

### Front-end (admin panel assets)

```
npm install
npm run dev          # / npm run development — one-off dev build
npm run watch         # rebuild on change
npm run hot           # webpack-dev-server with HMR
npm run production    # / npm run prod — minified production build
```

Built with Laravel Mix (`webpack.mix.js`), not Vite. Source lives in `resources/assets/`; **compiled output under `publishable/assets/` is committed to the repo** (this is a package — consumers get the compiled assets via `vendor:publish`, they don't run the build themselves). Any change to `resources/assets/**` must be followed by `npm run production` and the resulting `publishable/assets/**` diff committed alongside it — a GitHub Actions workflow (`compile-assets.yml`) also auto-PRs this if a raw asset change lands without a matching compiled-asset change.

The front-end is Vue 3 + Bootstrap 3 + jQuery (Select2, DataTables, TinyMCE, EasyMDE, Dropzone, Cropper.js, etc., all wired up in `resources/assets/js/app.js`). Most Vue components are **not** precompiled SFCs — they're registered with a runtime `template:` string pulled from a Blade `@yield()` section (see e.g. `resources/views/tools/database/vue-components/database-column.blade.php`), because Blade needs to interpolate PHP into the template. `webpack.mix.js`'s `.vue({ version: 3 })` must keep `runtimeOnly` at its default (`false`) or every one of those components breaks. Components are registered per-mount via a small helper, `resources/assets/js/voyager-vue.js`'s `createAdminApp(rootOptions, componentsMap)` (exposed as `window.VoyagerVue.createAdminApp` for Blade `<script>` blocks, which sit outside the webpack module graph) — Vue 3 has no global component registry, so pages that assemble components across multiple `@include`d Blade partials stage them into `window.voyagerComponents` before the final mount call. There are 7 independent Vue root mounts (admin menu, database table editor, two "table info" modals, the coordinates map field, the media manager, and the media picker field), each its own small app, not one SPA.

### CI

GitHub Actions (`.github/workflows/`) runs the PHPUnit suite across a PHP 8.3/8.4 × Laravel 13 matrix on push/PR to `1.*` branches, plus a separate coverage job (pcov, uploaded to Codecov). `compile-assets.yml` auto-compiles and auto-PRs when front-end source changes without matching compiled output.

## Architecture

### BREAD is the core abstraction

Most of Voyager's admin UI is generic CRUD driven by metadata, not per-model code. `data_types` (one row per manageable model/table) and `data_rows` (one row per field on that model, with its form-field type, validation, display rules, etc.) are database-driven config, edited through the "BREAD" builder UI itself (`resources/views/tools/bread/`) and consumed at runtime by `VoyagerBreadController` (`src/Http/Controllers/VoyagerBreadController.php`) to render Browse/Read/Edit/Add/Delete views generically for *any* registered model. Adding a new admin resource is usually "add a BREAD" through the UI, not writing a new controller.

### Form fields are pluggable

Each field type (text, date, image, code editor, markdown, rich text, media picker, coordinates, relationship, ...) is a class under `src/FormFields/` implementing a handler contract, registered on the `Voyager` facade/container (`src/Voyager.php`). The BREAD controller resolves the right handler per `data_rows` entry to render the form input and to process/store the submitted value. New field types are added by registering a new handler, not by branching inside the controller.

### Content-type handlers for special relationships

`src/Http/Controllers/ContentTypes/` holds per-type post-processing for BREAD fields that need extra handling beyond a simple form field (images, files, relationships, checkboxes) — these run alongside/after the FormFields layer when a BREAD row is saved.

### Database Manager talks to Schema/Blueprint directly, not Doctrine DBAL

`VoyagerDatabaseController` + `src/Database/` implement table/column/index browsing and editing on top of Laravel's native `Schema`/`Blueprint` API (with per-driver quirks under `src/Database/Platforms/`). This was a full rewrite (see CHANGELOG `[2.0.1]`) after Doctrine DBAL — which the old implementation depended on — was removed from Laravel core in v11. Don't reintroduce a `doctrine/dbal` dependency here.

### Everything is event-driven at the edges

`src/Events/` defines 30+ domain events (`BreadAdded`, `BreadDataUpdated`, `FileDeleted`, `SettingUpdated`, `TableAdded`, `MenuDisplay`, ...) fired around BREAD, media, menu, and settings operations, with `VoyagerEventServiceProvider` wiring listeners. Prefer hooking new cross-cutting behavior into an existing event rather than adding calls inline in controllers.

### Menus, Settings, Roles/Permissions are their own small subsystems

Each has a model (`src/Models/`), a controller, and Blade views under the matching `resources/views/` subdirectory (`menu/`, `settings/`, `roles/`, `users/`). Authorization is Laravel policies (`src/Policies/`) gated per-model, not a separate ACL system.

### Widgets and Alerts are the two small pluggable UI-extension points

`src/Widgets/` integrates `arrilot/laravel-widgets` for dashboard widgets. `src/Alert/` is a small component system (`AbstractComponent`/`ButtonComponent`/`TextComponent`/`TitleComponent`) for building admin-panel alert banners programmatically.

### Config and seed data are published into the host app

`publishable/` (config, migrations, seeders, compiled assets) is what `vendor:publish` copies into the consuming Laravel app — it is not run directly from within this package. `publishable/database/seeders/` seeds core Voyager data (data types/rows, roles, permissions, menus, settings); `publishable/database/dummy_seeders/` seeds the optional demo content (`--with-dummy`).
