<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Shared tracking service used by all form adapters.
 */
class PWTSR_Tracking_Service {

  /**
   * Return every key stored with a submission.
   *
   * With the 'both' model these are the last-touch keys followed by their first-touch copies;
   * otherwise the plain keys alone, holding whichever model is selected.
   *
   * @param string $context Integration context.
   *
   * @return string[]
   */
  public function get_tracking_keys( $context = 'core' ) {
    $keys = $this->get_capture_keys( $context );
    if ( PWTSR::MODEL_BOTH !== $this->get_attribution_model( $context ) ) {
      return $keys;
    }

    $first_touch_keys = array_map(
      static function ( $key ) {
        return PWTSR::FIRST_TOUCH_PREFIX . $key;
      },
      $keys
    );

    return array_values( array_unique( array_merge( $keys, $first_touch_keys ) ) );
  }

  /**
   * Return the attribution keys the browser captures (the last-touch set).
   *
   * @param string $context Integration context.
   *
   * @return string[]
   */
  public function get_capture_keys( $context = 'core' ) {
    $keys = array_merge( PWTSR::DEFAULT_TRACKING_KEYS, $this->get_custom_settings_keys() );
    $keys = apply_filters( PWTSR::FILTER_TRACKING_KEYS, $keys, $context );

    return $this->sanitize_keys( $keys );
  }

  /**
   * Whether the script waits for visitor consent before storing anything.
   *
   * @return bool
   */
  public function is_consent_required() {
    return (bool) apply_filters( PWTSR::FILTER_REQUIRE_CONSENT, 'on' === $this->get_setting( 'require_consent', 'off' ) );
  }

  /**
   * WP Consent API category the script waits for.
   *
   * @return string
   */
  public function get_consent_category() {
    $category = sanitize_key( (string) apply_filters( PWTSR::FILTER_CONSENT_CATEGORY, PWTSR::CONSENT_CATEGORY ) );

    return '' !== $category ? $category : PWTSR::CONSENT_CATEGORY;
  }

  /**
   * Return the attribution model: 'both', 'last', or 'first'.
   *
   * @param string $context Integration context.
   *
   * @return string
   */
  public function get_attribution_model( $context = 'core' ) {
    $model = apply_filters( PWTSR::FILTER_ATTRIBUTION_MODEL, $this->get_setting( 'attribution_model', PWTSR::MODEL_BOTH ), $context );

    return in_array( $model, PWTSR::ATTRIBUTION_MODELS, true ) ? $model : PWTSR::MODEL_BOTH;
  }

  /**
   * Return TTL seconds for a specific adapter context.
   *
   * @param string $context Integration context.
   *
   * @return int
   */
  public function get_ttl_seconds( $context = 'core' ) {
    $ttl = (int) apply_filters( PWTSR::FILTER_TRACKING_TTL, $this->get_setting_ttl_seconds(), $context );

    return $ttl > 0 ? $ttl : PWTSR::TTL_SECONDS;
  }

  /**
   * Expiry chosen on the settings screen, in seconds.
   *
   * @return int
   */
  private function get_setting_ttl_seconds() {
    $value = absint( $this->get_setting( 'expiry_value', 0 ) );
    $unit  = (string) $this->get_setting( 'expiry_unit', '' );

    if ( ! $value || ! isset( PWTSR::EXPIRY_UNITS[ $unit ] ) ) {
      return PWTSR::TTL_SECONDS;
    }

    return min( $value * PWTSR::EXPIRY_UNITS[ $unit ], PWTSR::TTL_MAX_SECONDS );
  }

  /**
   * Build client-side config payload.
   *
   * Storage key, TTL, and attribution model are page-wide, so they use the 'core' context; capture
   * keys are merged across every given context. The script derives first-touch keys itself.
   *
   * @param string|string[] $contexts Integration context or contexts.
   *
   * @return array
   */
  public function get_client_config( $contexts = 'core' ) {
    $storage_key = (string) apply_filters( PWTSR::FILTER_STORAGE_KEY, PWTSR::STORAGE_KEY, 'core' );
    if ( '' === $storage_key ) {
      $storage_key = PWTSR::STORAGE_KEY;
    }

    $keys = [];
    foreach ( (array) $contexts as $context ) {
      $keys = array_merge( $keys, $this->get_capture_keys( (string) $context ) );
    }

    return [
      'storageKey'       => $storage_key,
      'ttl'              => $this->get_ttl_seconds( 'core' ),
      'transceiverKeys'  => array_values( array_unique( $keys ) ),
      'derivedKeys'      => PWTSR::DERIVED_TRACKING_KEYS,
      'attributionModel' => $this->get_attribution_model( 'core' ),
      'firstTouchPrefix' => PWTSR::FIRST_TOUCH_PREFIX,
      'requireConsent'   => $this->is_consent_required(),
      'consentCategory'  => $this->get_consent_category(),
    ];
  }

  /**
   * Sanitize a single tracking value and enforce max length.
   *
   * @param string $key   Tracking key.
   * @param mixed  $value Tracking value.
   *
   * @return string
   */
  public function sanitize_tracking_value( $key, $value ) {
    if ( ! is_scalar( $value ) ) {
      return '';
    }

    $clean = $this->truncate_value( $this->strip_value( (string) $value ) );
    if ( '' === $clean ) {
      return '';
    }

    if ( in_array( $this->get_base_key( (string) $key ), [ 'landing_page', 'referrer' ], true ) ) {
      $clean = esc_url_raw( $clean );
      return $this->truncate_value( $clean );
    }

    return $clean;
  }

  /**
   * Strip the first-touch prefix so both copies of a key share its sanitizing rules.
   *
   * @param string $key Tracking key.
   *
   * @return string
   */
  private function get_base_key( $key ) {
    if ( 0 === strpos( $key, PWTSR::FIRST_TOUCH_PREFIX ) ) {
      return substr( $key, strlen( PWTSR::FIRST_TOUCH_PREFIX ) );
    }

    return $key;
  }

  /**
   * Reduce a value to a single line of plain text.
   *
   * Unlike sanitize_text_field(), this keeps percent-encoded octets, which landing
   * URLs, query strings, and referrers rely on.
   *
   * @param string $value Raw value.
   *
   * @return string
   */
  private function strip_value( $value ) {
    $value = wp_check_invalid_utf8( $value );
    $value = wp_strip_all_tags( $value, true );
    $value = preg_replace( '/[\x00-\x1F\x7F]+/', '', $value );

    return trim( (string) $value );
  }

  /**
   * Clamp string values to the plugin max length.
   *
   * @param string $value Raw value.
   *
   * @return string
   */
  public function truncate_value( $value ) {
    $value = (string) $value;
    if ( '' === $value ) {
      return '';
    }

    if ( function_exists( 'mb_substr' ) ) {
      return mb_substr( $value, 0, PWTSR::MAX_VALUE_LENGTH );
    }

    return substr( $value, 0, PWTSR::MAX_VALUE_LENGTH );
  }

  /**
   * Normalize and de-duplicate tracking keys.
   *
   * @param mixed $keys Raw key list.
   *
   * @return string[]
   */
  private function sanitize_keys( $keys ) {
    if ( ! is_array( $keys ) ) {
      return PWTSR::DEFAULT_TRACKING_KEYS;
    }

    $sanitized = [];
    foreach ( $keys as $key ) {
      if ( ! is_scalar( $key ) ) {
        continue;
      }

      $clean = sanitize_key( (string) $key );
      if ( '' === $clean ) {
        continue;
      }

      $sanitized[] = $clean;
    }

    $sanitized = array_values( array_unique( $sanitized ) );

    return ! empty( $sanitized ) ? $sanitized : PWTSR::DEFAULT_TRACKING_KEYS;
  }

  /**
   * Read one saved setting.
   *
   * @param string $name    Setting name.
   * @param mixed  $default Value when unset.
   *
   * @return mixed
   */
  private function get_setting( $name, $default ) {
    $settings = get_option( PWTSR::SETTINGS_KEY, [] );

    return is_array( $settings ) && isset( $settings[ $name ] ) ? $settings[ $name ] : $default;
  }

  /**
   * Read custom tracking keys from plugin settings.
   *
   * @return string[]
   */
  private function get_custom_settings_keys() {
    $settings = get_option( PWTSR::SETTINGS_KEY, [] );
    if ( ! is_array( $settings ) || empty( $settings['custom_params'] ) ) {
      return [];
    }

    if ( ! is_array( $settings['custom_params'] ) ) {
      return [];
    }

    return $settings['custom_params'];
  }
}
