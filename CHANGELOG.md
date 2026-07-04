# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.2.1] - 2026-07-03

### Fixed

- Several Bootstrap 5 admin-theme regressions left over from the 2.2.0 migration: the login button's "logging in"/"login" labels overlapping (still used the dropped `.hidden` class instead of `.d-none`) and losing its hover background; the sidebar/menu bundle failing to load (Vite build mixed `require()` with ES imports, silently dropping modules) along with related sidebar, submenu, and user-card styling; the profile dropdown not opening and shoving the avatar toggle sideways; breadcrumbs stacking vertically; the initial tab pane on Settings/Compass rendering blank; and DataTables' sort icons/pagination going unstyled (`datatables.net-bs5` had drifted to the 2.x line).
- Media manager crashing on `admin/media` (`Cannot read properties of undefined (reading 'type')`). Vue 3 evaluates `v-if` before binding the `v-for` loop variable when both are on the same element (reversed from Vue 2), so the file-list filter and the move-to-folder dropdown received `undefined`. Both are now computed properties evaluated before the loop.
- `php artisan voyager:admin --create` failing: a role-relationship migration re-declared `users.role_id` in `Blueprint::change()` without restating `nullable()`, which (without Doctrine DBAL) flips it to `NOT NULL` and breaks the insert-then-assign-role flow the command relies on.
- Admin menu rendering a `TypeError` under Laravel 11+'s default `cache.serializable_classes = false`, which rejects unserializing the cached hydrated `Menu` model. The menu is now cached by id (a scalar) and re-queried with its eager loads on each call.
- Published JS/CSS assets served with a one-year cache lifetime but a constant URL, so browsers kept stale bundles across upgrades until a hard refresh. `voyager_asset()` now appends a `&v=<mtime>` cache-busting token.

## [2.2.0] - 2026-07-02

### Changed

- Migrated the admin panel's build tooling from Laravel Mix to Vite. Mix is unmaintained and Vite is Laravel 13's default; since this package serves assets through its own route (`voyager_asset()`) rather than `@vite()`/manifest helpers, no `laravel-vite-plugin` was needed — a plain `vite.config.js` produces the same fixed, unhashed `publishable/assets/{css,js}` output. See #6 for the full writeup.
- Migrated the admin panel's theme from Bootstrap 3 to Bootstrap 5 across ~34 Blade views: `.panel` → `.card`, Bootstrap's data-API attributes → `data-bs-*`, grid/utility class renames, and glyphicons → Voyager's own icon font. `bootstrap-toggle`, `eonasdan-bootstrap-datetimepicker`, and `datatables-bootstrap3-plugin` (all unmaintained, Bootstrap-3-only) were replaced with Bootstrap 5's native switch markup, `@eonasdan/tempus-dominus`, and `datatables.net-bs5` respectively. This closes the last two open `npm audit` advisories from prior releases. See #5 for the full writeup.

### Fixed

- 3 CI workflows' Node version bumped from 16.x to 20.x (Vite 6 requires Node ≥18).

## [2.1.0] - 2026-07-02

### Changed

- Migrated the admin panel's front-end from Vue 2.7 to Vue 3. Vue 2 was EOL and had an open ReDoS advisory (GHSA-5j4c-8p2g-v4jx) with no Vue-2-compatible fix. This is an internal build/runtime change with no expected difference in admin panel behavior; see #3 for the full migration writeup. Bootstrap 3 and the rest of the front-end toolchain are unchanged and tracked separately.

### Fixed

- Updated `tinymce` (6.8 → 7.9.3) and `postcss`/`nanoid` to their latest patch releases, fixing several XSS advisories (including one high-severity) flagged by `npm audit`. `bootstrap`, `vue`'s previous ReDoS advisory (see above), and the `eonasdan-bootstrap-datetimepicker`/`moment-timezone` advisory remain open, since fixing them requires breaking changes to the admin theme that are out of scope for this release.

## [2.0.1] - 2026-07-02

### Breaking

- Minimum PHP version is now **8.3**. Older PHP versions are no longer supported.
- Minimum Laravel version is now **13.0**. Laravel 11 and 12 are no longer supported by this package version — if you need to stay on an older Laravel version, keep using a previous release of this package.

### Restored

- The **Database Manager** (browsing and editing tables, columns, and indexes) works again. It has been fully rewritten on top of Laravel's native `Schema`/`Blueprint` API, removing the dependency on Doctrine DBAL, which Laravel itself removed in v11. This re-enables a feature that had been broken since this fork moved to Laravel 11.

### Fixed

- Bootstrap-style pagination in the admin panel now actually renders Bootstrap markup. A string-literal bug in the `method_exists()` check that enables `Paginator::useBootstrap()` meant the guard always evaluated to `false`, so the admin panel had silently been rendering Laravel's default (Tailwind) pagination markup instead of Bootstrap markup. If your admin panel's pagination controls change appearance after upgrading, this is why — it's the intended, correct behavior.
- `describeTable()` now returns the `null` column flag as the historical `"YES"`/`"NO"` string (matching MySQL's `DESCRIBE` output) instead of a raw boolean. The boolean broke the strict comparison in `Column::make()` and would have displayed literal `true`/`false` instead of `YES`/`NO` in the database-manager's "Show Table Info" modal.

### Changed

- `laravel/ui` requirement tightened from `>=1.0` to `^4.0`. It remains a required dependency (it provides the `AuthenticatesUsers` trait used by Voyager's auth controller, which no longer ships in Laravel core).

### Internal

- The test suite was migrated off the abandoned `laravel/browser-kit-testing` / `orchestra/testbench-browser-kit` packages onto standard Testbench/Illuminate testing APIs. This has no effect on package consumers, but is relevant to contributors running the test suite.
- Fixed the remaining PHPUnit 12 API incompatibilities in the test suite (mock builder and data provider changes), so the full suite (138 tests) runs clean in CI.

### Known limitations

- `intervention/image` remains on `^2.7`. It works correctly on PHP 8.3/8.4, but emits PHP 8.4 deprecation notices ("Implicitly marking parameter as nullable"). Migrating to `intervention/image` v3 is deferred to a future release; track this if you're running with deprecation notices treated as errors.
- Frontend build tooling (Laravel Mix, Vue 2, Bootstrap 3) is unchanged in this release. It continues to work, but modernizing it (e.g. to Vite/Vue 3) is deferred to a separate future release.
