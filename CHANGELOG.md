# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
