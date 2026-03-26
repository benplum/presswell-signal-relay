# Presswell Tracking Signal Relay Tests

## One-time setup

1. `cd /Users/bp/Sites/blockparty/wp-content/plugins/presswell-signal-relay`
2. `composer install`
3. `bin/install-wp-tests.sh wordpress_test root '' localhost latest`

If your shell resolves `TMPDIR` to a non-standard path and the suite cannot locate test libs, run with an explicit path:

- `WP_TESTS_DIR=/Users/bp/Sites/_labs/blockparty/.tmp-test-env/wordpress-tests-lib composer phpunit`

## Run tests (Composer shortcuts)

- Full suite: `composer phpunit`
- Single file: `composer phpunit:file -- tests/TrackingServiceTest.php`
- Single test method: `composer phpunit:filter -- --filter test_get_client_config_uses_fallback_storage_key_when_filter_returns_empty tests/TrackingServiceTest.php`

## Run tests (direct PHPUnit)

- Full suite: `vendor/bin/phpunit`
- Single file: `vendor/bin/phpunit tests/SettingsTest.php`
- Single test method: `vendor/bin/phpunit --filter test_should_show_debug_styles_respects_role_and_setting tests/SettingsTest.php`

## Optional WordPress test groups

By default, WordPress prints notices that some groups are skipped. You can run them explicitly:

- AJAX group: `vendor/bin/phpunit --group ajax`
- Multisite files group: `vendor/bin/phpunit --group ms-files`
- External HTTP group: `vendor/bin/phpunit --group external-http`

## Test coverage map

- `CorePluginIntegrationTest.php`: singleton bootstrap, core hook registration, settings link wiring, and tracking service accessor checks.
- `SettingsTest.php`: settings defaults, sanitization, custom parameter normalization, and debug visibility gate behavior.
- `TrackingServiceTest.php`: tracking-key assembly, TTL/storage filter behavior, and value sanitization (scalar/url/length constraints).
- `AdapterRegistryTest.php`: adapter registration key normalization, overwrite behavior, and `register_all()` dispatch.
- `AdapterBootstrapTest.php`: integration bootstrap idempotence and zero-dependency behavior in clean environments.
- `ContactForm7AdapterTest.php`: hidden input injection, posted value sanitization, mail-tag suggestions, and special mail-tag output.
- `WPFormsAdapterTest.php`: transceiver field payload sanitization and smart-tag replacement behavior.
- `GravityFormsAdapterTest.php`: duplicate tracking-field enforcement and submission input sanitization.
- `ForminatorAdapterTest.php`: hidden-input injection, entry payload append behavior, iterator display enrichment, and placeholder replacement.
- `FormidableAdapterTest.php`: field registration metadata, submission normalization, display formatting, and token replacement behavior.
- `FluentFormsAdapterTest.php`: smartcode registration/resolution, tracking-all email token formatting, and submission response normalization.

## Useful notes

- Full suite should pass with `Exit code 0`; if failures mention missing WordPress test libs, rerun `bin/install-wp-tests.sh`.
- Integration adapters are loaded conditionally and are intentionally not asserted here against third-party plugin classes.
