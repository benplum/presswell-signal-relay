<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Fluent Forms integration hooks.
 */
trait PWTSR_Fluent_Forms_Trait {

  /**
   * Guard against duplicate Fluent field manager registration.
   *
   * @var bool
   */
  private $fluent_field_registered = false;

  /**
   * Register Fluent Forms hooks. The bootstrap only loads this adapter when Fluent Forms is active.
   */
  protected function construct_fluent_forms_trait() {
    $this->register_fluent_forms_transceiver_field();

    add_action( 'fluentform/before_form_render', [ $this, 'maybe_enqueue_fluent_forms_assets' ], 20, 2 );

    add_filter( 'fluentform/insert_response_data', [ $this, 'sanitize_fluent_forms_tracking_response' ], 20, 3 );

    add_filter( 'fluentform/response_render_' . PWTSR::FIELD_TYPE, [ $this, 'render_fluent_forms_tracking_response' ], 10, 4 );
    add_filter( 'fluentform/form_settings_smartcodes', [ $this, 'register_fluent_forms_tracking_smartcodes' ], 20, 2 );
    add_filter( 'fluentform/smartcode_group_tracking', [ $this, 'resolve_fluent_forms_tracking_smartcode' ], 20, 2 );
    add_filter( 'fluentform/email_body', [ $this, 'format_fluent_forms_tracking_all_email_body' ], 20, 4 );
    add_filter( 'fluentform/email_subject', [ $this, 'format_fluent_forms_tracking_all_email_subject' ], 20, 4 );
  }

  /**
   * Register Presswell transceiver field type for Fluent Forms editor.
   */
  public function register_fluent_forms_transceiver_field() {
    if ( $this->fluent_field_registered ) {
      return;
    }

    if ( ! class_exists( 'FluentForm\\App\\Services\\FormBuilder\\BaseFieldManager' ) || ! class_exists( 'PWTSR_Fluent_Forms_Field' ) ) {
      return;
    }

    new PWTSR_Fluent_Forms_Field();
    $this->fluent_field_registered = true;
  }

  /**
   * Enqueue tracking script only for forms that include the transceiver field.
   *
   * @param mixed $form Fluent form model.
   * @param array $atts Render attributes.
   */
  public function maybe_enqueue_fluent_forms_assets( $form = null, $atts = [] ) {
    if ( ! $this->fluent_form_has_transceiver_field( $form ) ) {
      return;
    }

    $this->enqueue_tracking_script();
  }

  /**
   * Reduce the submitted tracking group to known keys with sanitized values.
   *
   * Fluent Forms stores the transceiver field's posted array as-is (after its own
   * sanitize_text_field pass), so this whitelists keys and applies the plugin's URL
   * handling and length limit before the response and entry details are written.
   *
   * @param array $form_data     Submitted form data.
   * @param int   $form_id       Form ID.
   * @param array $input_configs Form input configuration.
   *
   * @return array
   */
  public function sanitize_fluent_forms_tracking_response( $form_data, $form_id = 0, $input_configs = [] ) {
    if ( ! is_array( $form_data ) || ! array_key_exists( PWTSR::FIELD_NAME, $form_data ) ) {
      return $form_data;
    }

    $posted = is_array( $form_data[PWTSR::FIELD_NAME] ) ? $form_data[PWTSR::FIELD_NAME] : [];
    $pairs  = [];

    foreach ( $this->service->get_tracking_keys( PWTSR::ADAPTER_FLUENT_FORMS ) as $key ) {
      if ( ! isset( $posted[ $key ] ) || ! is_scalar( $posted[ $key ] ) ) {
        continue;
      }

      $clean = $this->service->sanitize_tracking_value( $key, $posted[ $key ] );
      if ( '' !== $clean ) {
        $pairs[ $key ] = $clean;
      }
    }

    $form_data[PWTSR::FIELD_NAME] = $pairs;

    return $form_data;
  }

  /**
   * Register tracking smartcodes for notification/editor UI.
   *
   * @param array $groups Existing smartcode groups.
   * @param mixed $form   Fluent form object.
   *
   * @return array
   */
  public function register_fluent_forms_tracking_smartcodes( $groups, $form ) {
    if ( ! is_array( $groups ) ) {
      $groups = [];
    }

    $shortcodes = [];

    $shortcodes['{tracking.all}'] = __( 'All Tracking Signals', 'presswell-signal-relay' );

    foreach ( $this->service->get_tracking_keys( PWTSR::ADAPTER_FLUENT_FORMS ) as $key ) {
      $shortcodes[ '{tracking.' . $key . '}' ] = $this->build_fluent_forms_tracking_label( $key );
    }

    if ( empty( $shortcodes ) ) {
      return $groups;
    }

    $groups[] = [
      'title'      => __( 'Tracking Signals', 'presswell-signal-relay' ),
      'shortcodes' => $shortcodes,
    ];

    return $groups;
  }

  /**
   * Resolve {tracking.*} smartcodes at send/render time.
   *
   * @param string $property Smartcode property, e.g. utm_source.
   * @param mixed  $parser   ShortCodeParser instance.
   *
   * @return string
   */
  public function resolve_fluent_forms_tracking_smartcode( $property, $parser ) {
    if ( ! is_string( $property ) || '' === $property ) {
      return $property;
    }

    $entry = null;
    if ( is_object( $parser ) && method_exists( $parser, 'getEntry' ) ) {
      $entry = $parser::getEntry();
    }

    if ( ! is_object( $entry ) ) {
      return '';
    }

    $response = $this->extract_fluent_forms_submission_response_array( $entry );
    if ( empty( $response ) ) {
      return '';
    }

    $pairs = $this->extract_fluent_forms_tracking_pairs( $response );

    if ( 'all' === $property ) {
      $pairs = [];
      foreach ( $this->extract_fluent_forms_tracking_pairs( $response ) as $key => $pair_value ) {
        if ( '' === (string) $pair_value ) {
          continue;
        }

        $pairs[] = $this->build_fluent_forms_tracking_label( $key ) . ': ' . (string) $pair_value;
      }

      $all_output = implode( "\n", $pairs );
      if ( $this->is_fluent_forms_notification_smartcode_context( $parser ) ) {
        return $this->wrap_fluent_forms_tracking_all_token( $all_output );
      }

      return $all_output;
    }

    if ( ! isset( $pairs[ $property ] ) ) {
      return '';
    }

    return (string) $pairs[ $property ];
  }

  /**
   * Convert tokenized tracking.all value to HTML/Plain text for outgoing email body.
   *
   * @param mixed $email_body   Parsed email body.
   * @param array $notification Notification settings.
   * @param array $submitted    Submitted form values.
   * @param mixed $form         Fluent form object.
   *
   * @return mixed
   */
  public function format_fluent_forms_tracking_all_email_body( $email_body, $notification, $submitted, $form ) {
    if ( ! is_string( $email_body ) || false === strpos( $email_body, '[[PWTSR_TRACKING_ALL]]' ) ) {
      return $email_body;
    }

    $is_plain = is_array( $notification ) && isset( $notification['asPlainText'] ) && 'yes' === (string) $notification['asPlainText'];

    return preg_replace_callback(
      '/\[\[PWTSR_TRACKING_ALL\]\](.*?)\[\[\/PWTSR_TRACKING_ALL\]\]/s',
      function ( $matches ) use ( $is_plain ) {
        $content = str_replace( [ "\r\n", "\r" ], "\n", (string) $matches[1] );

        if ( $is_plain ) {
          return $content;
        }

        return str_replace( "\n", '<br />', $content );
      },
      $email_body
    );
  }

  /**
   * Strip token markers/newlines from subject if tracking.all is used there.
   *
   * @param mixed $subject      Parsed subject.
   * @param array $notification Notification settings.
   * @param array $submitted    Submitted form values.
   * @param mixed $form         Fluent form object.
   *
   * @return mixed
   */
  public function format_fluent_forms_tracking_all_email_subject( $subject, $notification, $submitted, $form ) {
    if ( ! is_string( $subject ) || false === strpos( $subject, 'PWTSR_TRACKING_ALL' ) ) {
      return $subject;
    }

    $subject = preg_replace( '/\[\[\/?PWTSR_TRACKING_ALL\]\]/', '', $subject );
    $subject = preg_replace( '/\s*[\r\n]+\s*/', ' | ', $subject );

    return trim( $subject );
  }

  /**
   * Format the stored tracking group wherever Fluent Forms displays the field's value.
   *
   * Covers the entry screen, exports, and {all_data}; without it Fluent prints the raw
   * object, which shows as [object Object].
   *
   * @param mixed $value    Stored field value, decoded from the response JSON.
   * @param array $field    Field config.
   * @param int   $form_id  Form ID.
   * @param bool  $is_html  Whether HTML output is wanted.
   *
   * @return string
   */
  public function render_fluent_forms_tracking_response( $value, $field = [], $form_id = 0, $is_html = false ) {
    $group = is_object( $value ) ? get_object_vars( $value ) : $value;
    $pairs = $this->extract_fluent_forms_tracking_pairs( [ PWTSR::FIELD_NAME => is_array( $group ) ? $group : [] ] );

    if ( empty( $pairs ) ) {
      return '';
    }

    if ( ! $is_html ) {
      $lines = [];
      foreach ( $pairs as $key => $pair_value ) {
        $lines[] = $key . ': ' . $pair_value;
      }

      return implode( ', ', $lines );
    }

    $items = '';
    foreach ( $pairs as $key => $pair_value ) {
      $items .= sprintf( '<li><strong>%s:</strong> %s</li>', esc_html( $key ), esc_html( $pair_value ) );
    }

    return '<ul class="ff_entry_list">' . $items . '</ul>';
  }

  /**
   * Build a readable label from a tracking key.
   *
   * @param string $key Tracking key.
   *
   * @return string
   */
  private function build_fluent_forms_tracking_label( $key ) {
    return ucwords( str_replace( '_', ' ', (string) $key ) );
  }

  /**
   * Wrap tracking.all content with a token marker for downstream email formatting.
   *
   * @param string $content Tracking lines.
   *
   * @return string
   */
  private function wrap_fluent_forms_tracking_all_token( $content ) {
    return '[[PWTSR_TRACKING_ALL]]' . (string) $content . '[[/PWTSR_TRACKING_ALL]]';
  }

  /**
   * Determine whether current smartcode parse context is Fluent notifications.
   *
   * @param mixed $parser ShortCodeParser instance.
   *
   * @return bool
   */
  private function is_fluent_forms_notification_smartcode_context( $parser ) {
    if ( ! is_object( $parser ) || ! method_exists( $parser, 'getProvider' ) ) {
      return false;
    }

    return 'notifications' === (string) $parser::getProvider();
  }

  /**
   * Normalize Fluent submission response payload to array.
   *
   * @param object $submission Submission or entry object.
   *
   * @return array
   */
  private function extract_fluent_forms_submission_response_array( $submission ) {
    if ( ! is_object( $submission ) || ! isset( $submission->response ) ) {
      return [];
    }

    if ( is_array( $submission->response ) ) {
      return $submission->response;
    }

    if ( is_object( $submission->response ) ) {
      return (array) $submission->response;
    }

    if ( is_string( $submission->response ) && '' !== $submission->response ) {
      $decoded = json_decode( $submission->response, true );
      return is_array( $decoded ) ? $decoded : [];
    }

    return [];
  }

  /**
   * Extract tracking pairs from a Fluent response payload.
   *
   * @param array $response Submission response payload.
   *
   * @return array
   */
  private function extract_fluent_forms_tracking_pairs( $response ) {
    if ( ! is_array( $response ) ) {
      return [];
    }

    $pairs = [];
    $keys  = $this->service->get_tracking_keys( PWTSR::ADAPTER_FLUENT_FORMS );

    if ( isset( $response[PWTSR::FIELD_NAME] ) && is_array( $response[PWTSR::FIELD_NAME] ) ) {
      foreach ( $keys as $key ) {
        if ( ! isset( $response[PWTSR::FIELD_NAME][ $key ] ) || is_array( $response[PWTSR::FIELD_NAME][ $key ] ) ) {
          continue;
        }

        $clean = $this->service->sanitize_tracking_value( $key, $response[PWTSR::FIELD_NAME][ $key ] );
        if ( '' !== $clean ) {
          $pairs[ $key ] = $clean;
        }
      }
    }

    foreach ( $keys as $key ) {
      if ( isset( $pairs[ $key ] ) ) {
        continue;
      }

      if ( ! isset( $response[ $key ] ) || is_array( $response[ $key ] ) ) {
        continue;
      }

      $clean = $this->service->sanitize_tracking_value( $key, $response[ $key ] );
      if ( '' !== $clean ) {
        $pairs[ $key ] = $clean;
      }
    }

    return $pairs;
  }

  /**
   * Determine if a Fluent form contains the Presswell transceiver field.
   *
   * @param mixed $form Fluent form model.
   *
   * @return bool
   */
  private function fluent_form_has_transceiver_field( $form ) {
    if ( ! is_object( $form ) || empty( $form->fields ) || ! is_array( $form->fields ) || empty( $form->fields['fields'] ) || ! is_array( $form->fields['fields'] ) ) {
      return false;
    }

    foreach ( $form->fields['fields'] as $field ) {
      if ( ! is_array( $field ) || empty( $field['element'] ) ) {
        continue;
      }

      if ( PWTSR::FIELD_TYPE === $field['element'] ) {
        return true;
      }
    }

    return false;
  }
}
