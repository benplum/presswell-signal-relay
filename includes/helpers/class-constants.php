<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Central constants singleton for plugin-wide identifiers.
 */
final class PWSL {

  const VERSION = '1.1.0';
  const TEXT_DOMAIN = 'presswell-signal-relay';

  const FIELD_TYPE = 'presswell_transceiver';

  const ASSET_HANDLE_SCRIPT = 'presswell-signal-relay-js';
  const ASSET_HANDLE_STYLE = 'presswell-signal-relay-css';

  const STORAGE_KEY = 'presswellSignalRelay';
  const TTL_SECONDS = 3600;
  const MAX_VALUE_LENGTH = 1024;

  const JS_OBJECT = 'presswellSignalRelayConfig';

  const ADAPTER_GRAVITY_FORMS = 'gravityforms';
  const ADAPTER_FORMINATOR = 'forminator';

  /**
   * Cached singleton instance.
   *
   * @var PWSL|null
   */
  private static $instance = null;

  /**
   * Prevent direct instantiation.
   */
  private function __construct() {}

  /**
   * Return the shared constants singleton instance.
   *
   * @return PWSL
   */
  public static function instance() {
    if ( null === self::$instance ) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Default ordered list of attribution keys.
   *
   * @return string[]
   */
  public static function default_tracking_keys() {
    return [
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
  }
}
