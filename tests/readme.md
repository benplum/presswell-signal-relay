# Presswell Tracking Signal Relay Tests

## One-time setup

1. `composer install`
2. `bin/install-wp-tests.sh <db-name> <db-user> '<db-pass>' localhost latest` — use a database only for tests; the suite empties it.

The bootstrap finds the test library in `WP_TESTS_DIR`, then `$TMPDIR/wordpress-tests-lib`, then `/tmp/wordpress-tests-lib`. macOS clears `$TMPDIR` from time to time; if the library goes missing, run the install script again or point `WP_TESTS_DIR` at a lasting copy.

## Run tests

- Full suite: `vendor/bin/phpunit`
- One file: `vendor/bin/phpunit tests/SettingsTest.php`
- One test: `vendor/bin/phpunit --filter test_ttl_comes_from_expiry_setting`

The form plugins aren't loaded. Adapter tests run each adapter's trait in a small harness, with stand-ins for the form plugin classes they touch (`GF_Field`, `rgar()`, `FrmEntryMeta`, `WPCF7_Submission`, ...).

## Coverage

- `CorePluginIntegrationTest.php`: singleton, hook registration, settings link, and admin styles loading only on the settings screen.
- `SettingsTest.php`: defaults, sanitizing (attribution model, expiry, consent, Remove Data, custom parameters), the settings fields, and the debug display gate.
- `TrackingServiceTest.php`: tracking keys and their `first_` copies, attribution model, expiry, filters, client config, and value sanitizing (URLs, percent-encoding, tags, line breaks, length).
- `AssetsTraitTest.php`: the script loading site-wide while an adapter is active, the opt-out filter, and one config with keys from every adapter.
- `PrivacyTraitTest.php`: suggested privacy policy text, consent settings in the client config, and WP Consent API registration.
- `UninstallTest.php`: settings kept by default and removed with Remove Data.
- `TransceiverMarkupTest.php`: wrapper and input markup, the debug panel, and escaping by content type (URL, HTML, text).
- `AdapterAssetsTraitTest.php`: the shared adapter helpers in normal and debug mode.
- `AdapterRegistryTest.php`, `AdapterBootstrapTest.php`: adapter registration and bootstrap.
- `ContactForm7AdapterTest.php`: hidden inputs, posted data sanitizing, and mail tags.
- `FluentFormsAdapterTest.php`: response sanitizing, entry display, smartcodes, and email formatting.
- `FormidableAdapterTest.php`: field registration, submission sanitizing, display, and tokens escaped for URLs and HTML.
- `ForminatorAdapterTest.php`: hidden inputs, entry values, and placeholders, including HTML emails.
- `GravityFormsAdapterTest.php`: sanitizing on save, inputs matched by key, the entry list, and merge tags escaped for HTML.
- `WPFormsAdapterTest.php`: smart tags, including older WPForms versions.

The browser script (`assets/js/transceiver.js`) has no automated tests; check it with the query strings in `AGENTS.md`.
