<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Shared transceiver wrapper markup helpers.
 */
final class PWTSR_Transceiver_Markup {

  /**
   * Allowed tags/attributes for transceiver wrapper output.
   *
   * @return array
   */
  public static function get_wrapper_allowed_html() {
    return [
      'div' => [
        'class' => true,
        'data-pwtsr-transceiver' => true,
        'data-pwtsr-transceiver-adapter' => true,
        'aria-hidden' => true,
        'style' => true,
        'hidden' => true,
      ],
      'button' => [
        'type' => true,
        'class' => true,
        'aria-expanded' => true,
      ],
      'p' => [
        'class' => true,
        'hidden' => true,
      ],
      'span' => [
        'class' => true,
        'aria-hidden' => true,
      ],
      'label' => [
        'class' => true,
        'for' => true,
      ],
      'input' => [
        'type' => true,
        'id' => true,
        'name' => true,
        'value' => true,
        'readonly' => true,
        'data-pwtsr-transceiver' => true,
      ],
    ];
  }

  /**
   * Sanitize rendered transceiver wrapper markup with a strict allowlist.
   *
   * @param string $markup Wrapper markup.
   *
   * @return string
   */
  public static function sanitize_wrapper_markup( $markup ) {
    return wp_kses(
      (string) $markup,
      self::get_wrapper_allowed_html()
    );
  }

  /**
   * Whether the current visitor sees the debug display instead of hidden inputs.
   *
   * @return bool
   */
  public static function is_debug_mode() {
    return function_exists( 'presswell_tracking_signal_relay' ) && presswell_tracking_signal_relay()->should_show_debug_styles();
  }

  /**
   * Render one tracking input: hidden, or a labelled read-only row in debug mode.
   *
   * @param string $key        Tracking key the script fills in.
   * @param string $name       Input name attribute.
   * @param string $id         Input id attribute.
   * @param bool   $debug_mode Whether debug mode is active.
   * @param string $value      Current value, when editing a saved entry.
   *
   * @return string
   */
  public static function render_input( $key, $name, $id, $debug_mode, $value = '' ) {
    if ( $debug_mode ) {
      return sprintf(
        '<div class="pwtsr-debug-field-row"><label class="pwtsr-debug-field-label" for="%1$s">%2$s</label><input type="text" id="%1$s" name="%3$s" value="%4$s" readonly="readonly" data-pwtsr-transceiver="%5$s" /></div>',
        esc_attr( $id ),
        esc_html( $key ),
        esc_attr( $name ),
        esc_attr( $value ),
        esc_attr( $key )
      );
    }

    return sprintf(
      '<input type="hidden" id="%1$s" name="%2$s" value="%3$s" data-pwtsr-transceiver="%4$s" />',
      esc_attr( $id ),
      esc_attr( $name ),
      esc_attr( $value ),
      esc_attr( $key )
    );
  }

  /**
   * Wrap rendered inputs in the shared container, sanitized for output.
   *
   * @param string $adapter       Adapter slug.
   * @param string $inputs        Inputs markup.
   * @param bool   $debug_mode    Whether debug mode is active.
   * @param string $extra_classes Additional wrapper classes.
   *
   * @return string
   */
  public static function render( $adapter, $inputs, $debug_mode, $extra_classes = '' ) {
    $adapter = sanitize_html_class( (string) $adapter );
    $classes = trim( 'pwtsr-transceiver pwtsr-' . $adapter . '-transceiver ' . $extra_classes );

    return self::sanitize_wrapper_markup( self::render_wrapper( $classes, $adapter, $inputs, $debug_mode ) );
  }

  /**
   * Determine whether debug panel should start closed based on cookie state.
   *
   * @return bool
   */
  public static function is_debug_panel_closed_from_cookie() {
    if ( empty( $_COOKIE[ PWTSR::DEBUG_COOKIE ] ) ) {
      return false;
    }

    $raw = sanitize_text_field( wp_unslash( $_COOKIE[ PWTSR::DEBUG_COOKIE ] ) );

    return '1' === (string) $raw;
  }

  /**
   * Render wrapper markup for tracking inputs.
   *
   * @param string $classes    Space-separated wrapper classes.
   * @param string $adapter    Adapter identifier.
   * @param string $inputs     Inputs markup.
   * @param bool   $debug_mode Whether debug mode is active.
   *
   * @return string
   */
  public static function render_wrapper( $classes, $adapter, $inputs, $debug_mode ) {
    if ( '' === trim( (string) $inputs ) ) {
      return '';
    }

    $adapter_attr = sanitize_html_class( (string) $adapter );
    $classes_attr = esc_attr( (string) $classes );

    if ( $debug_mode ) {
      $is_open = ! self::is_debug_panel_closed_from_cookie();

      return sprintf(
        '<div class="%1$s" data-pwtsr-transceiver="1" data-pwtsr-transceiver-adapter="%2$s"><button type="button" class="pwtsr-debug-toggle" aria-expanded="%3$s"><span class="pwtsr-debug-toggle-label">%6$s</span><span class="pwtsr-debug-toggle-arrow" aria-hidden="true"></span></button><div class="pwtsr-debug-fields"%4$s><p class="pwtsr-debug-consent" hidden>%7$s</p>%5$s</div></div>',
        $classes_attr,
        esc_attr( $adapter_attr ),
        $is_open ? 'true' : 'false',
        $is_open ? '' : ' hidden',
        $inputs,
        esc_html__( 'Tracking Signals', 'presswell-signal-relay' ),
        // Shown by the script while it waits for visitor consent, so empty fields have a visible cause.
        esc_html__( 'Waiting for visitor consent. Nothing is stored until it is given.', 'presswell-signal-relay' )
      );
    }

    return sprintf(
      '<div class="%1$s" data-pwtsr-transceiver="1" data-pwtsr-transceiver-adapter="%2$s" style="display:none" aria-hidden="true">%3$s</div>',
      $classes_attr,
      esc_attr( $adapter_attr ),
      $inputs
    );
  }

  /**
   * Guess what kind of content a merge tag is being replaced in.
   *
   * Some form plugins run one filter over success messages (HTML), redirect URLs, and email
   * subjects and bodies (plain text or HTML) without saying which.
   *
   * @param string $content Content containing tracking tags.
   *
   * @return string 'url', 'html', or 'text'.
   */
  public static function get_content_context( $content ) {
    $trimmed = trim( (string) $content );

    if ( preg_match( '#^(https?:)?//\S+$|^/\S*$#i', $trimmed ) ) {
      return 'url';
    }

    return false !== strpos( (string) $content, '<' ) ? 'html' : 'text';
  }

  /**
   * Escape a tag value for the content it is inserted into.
   *
   * @param string $value   Sanitized tracking value.
   * @param string $context 'url', 'html', or 'text'.
   *
   * @return string
   */
  public static function escape_for_context( $value, $context ) {
    if ( 'url' === $context ) {
      return rawurlencode( (string) $value );
    }

    // Values are already free of tags; escaping in HTML keeps quotes from closing an attribute.
    return 'html' === $context ? esc_html( (string) $value ) : (string) $value;
  }

  /**
   * Join "key: value" lines for an all-values tag, escaped for the context.
   *
   * @param array  $pairs   Tracking key/value pairs.
   * @param string $context 'url', 'html', or 'text'.
   *
   * @return string
   */
  public static function format_all_values( $pairs, $context ) {
    $lines = [];
    foreach ( $pairs as $key => $value ) {
      $lines[] = self::escape_for_context( $key . ': ' . $value, $context );
    }

    return implode( 'html' === $context ? '<br />' : "\n", $lines );
  }
}
