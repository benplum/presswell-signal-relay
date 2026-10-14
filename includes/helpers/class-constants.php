<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Central constants singleton for plugin-wide identifiers.
 */
final class PWTSR {

  const KEY = 'pwtsr';
  const VERSION = '1.0.0';
  const TEXT_DOMAIN = 'presswell-signal-relay';
  const SETTINGS_KEY = 'pwtsr_settings';
  const SETTINGS_GROUP = 'pwtsr_settings_group';
  const SETTINGS_SECTION = 'pwtsr_main_section';
  const SETTINGS_PAGE_SLUG = 'presswell-signal-relay';
  const SETTINGS_PAGE_URL = 'options-general.php?page=' . self::SETTINGS_PAGE_SLUG;
  const SETTINGS_PAGE_SCREEN_ID = 'settings_page_' . self::SETTINGS_PAGE_SLUG;

  const FILTER_TRACKING_KEYS = 'pwtsr_tracking_keys';
  const FILTER_TRACKING_TTL = 'pwtsr_tracking_ttl';
  const FILTER_STORAGE_KEY = 'pwtsr_storage_key';
  const FILTER_ATTRIBUTION_MODEL = 'pwtsr_attribution_model';
  const FILTER_REQUIRE_CONSENT = 'pwtsr_require_consent';
  const FILTER_CONSENT_CATEGORY = 'pwtsr_consent_category';
  const FILTER_ENQUEUE_TRACKING_SCRIPT = 'pwtsr_enqueue_tracking_script';

  // Field type registered with each form builder, and the input name for builders that store one value.
  const FIELD_TYPE = 'presswell_transceiver';
  const FIELD_NAME = 'pwtsr_tracking';

  const ASSET_HANDLE_SCRIPT = 'presswell-signal-relay-js';
  const ASSET_HANDLE_STYLE = 'presswell-signal-relay-css';
  const ASSET_HANDLE_ADMIN_STYLE = 'presswell-signal-relay-admin-css';
  const ASSET_HANDLE_ADMIN_SCRIPT = 'presswell-signal-relay-admin-js';
  const ASSET_HANDLE_DEBUG_STYLE = 'presswell-signal-relay-debug-css';
  const ASSET_HANDLE_DEBUG_SCRIPT = 'presswell-signal-relay-debug-js';
  const ASSET_HANDLE_GRAVITY_EDITOR_SCRIPT = 'presswell-signal-relay-gravity-editor-js';
  const ASSET_HANDLE_BUILDER_ICONS_STYLE = 'presswell-signal-relay-builder-icons';

  const STORAGE_KEY = 'pwSignalRelay';
  const EXPIRY_DEFAULT_VALUE = 7;
  const EXPIRY_DEFAULT_UNIT = 'days';
  const TTL_SECONDS = self::EXPIRY_DEFAULT_VALUE * DAY_IN_SECONDS;
  const TTL_MAX_SECONDS = 365 * DAY_IN_SECONDS;

  const EXPIRY_UNITS = [
    'hours' => HOUR_IN_SECONDS,
    'days'  => DAY_IN_SECONDS,
  ];

  const MODEL_BOTH = 'both';
  const MODEL_LAST_TOUCH = 'last';
  const MODEL_FIRST_TOUCH = 'first';
  const ATTRIBUTION_MODELS = [ self::MODEL_BOTH, self::MODEL_LAST_TOUCH, self::MODEL_FIRST_TOUCH ];
  const MAX_VALUE_LENGTH = 1024;

  const JS_OBJECT = 'PWTSR_CONFIG';
  const JS_OBJECT_GRAVITY_EDITOR = 'PWTSR_GRAVITY_EDITOR';

  // Remembers whether the debug panel was collapsed; also read by assets/js/debug.js.
  const DEBUG_COOKIE = 'pwtsr_debug_closed';

  // WP Consent API category whose consent the script waits for, when consent is required.
  const CONSENT_CATEGORY = 'marketing';

  const ADAPTER_CONTACT_FORM_7 = 'contactform7';
  const ADAPTER_FLUENT_FORMS = 'fluentforms';
  const ADAPTER_FORMIDABLE = 'formidable';
  const ADAPTER_FORMINATOR = 'forminator';
  const ADAPTER_GRAVITY_FORMS = 'gravityforms';
  const ADAPTER_WPFORMS = 'wpforms';

  const WPFORMS_MIN_VERSION = '1.9.1';

  const DEFAULT_TRACKING_KEYS = [
    'utm_source',
    'utm_medium',
    'utm_campaign',
    'utm_content',
    'utm_term',
    'gclid',
    'fbclid',
    'msclkid',
    'ttclid',
    'landing_page',
    'landing_query',
    'referrer',
  ];

  // Filled in by the script from the page itself, never read from the query string.
  const DERIVED_TRACKING_KEYS = [
    'landing_page',
    'landing_query',
    'referrer',
  ];

  const FIRST_TOUCH_PREFIX = 'first_';

}
