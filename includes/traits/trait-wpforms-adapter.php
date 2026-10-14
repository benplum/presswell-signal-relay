<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * WPForms integration hooks.
 */
trait PWTSR_WPForms_Trait {

  /**
   * Guard against registering the custom field type twice.
   *
   * @var bool
   */
  private $wpforms_field_registered = false;

  /**
   * Register WPForms hooks. The bootstrap only loads this adapter when WPForms is active.
   */
  protected function construct_wpforms_trait() {
    add_action( 'wpforms_frontend_output_before', [ $this, 'maybe_enqueue_wpforms_assets' ], 20, 2 );

    add_filter( 'wpforms_smart_tags', [ $this, 'register_wpforms_tracking_smart_tags' ], 20, 1 );
    add_filter( 'wpforms_smarttags_process_value', [ $this, 'replace_wpforms_tracking_smart_tags' ], 20, 7 );

    // Adapters register on init, after WPForms has loaded its field classes.
    $this->register_wpforms_transceiver_field();
  }

  /**
   * Register the custom transceiver field class with WPForms.
   */
  public function register_wpforms_transceiver_field() {
    if ( $this->wpforms_field_registered ) {
      return;
    }

    if ( ! class_exists( 'WPForms_Field' ) ) {
      return;
    }

    require_once dirname( __DIR__ ) . '/adapters/class-wpforms-field.php';

    if ( ! class_exists( 'PWTSR_WPForms_Field' ) ) {
      return;
    }

    new PWTSR_WPForms_Field();
    $this->wpforms_field_registered = true;
  }

  /**
   * Enqueue runtime assets only when a form includes the transceiver field.
   *
   * @param array $form_data Prepared form data.
   * @param mixed $form      Original form object.
   */
  public function maybe_enqueue_wpforms_assets( $form_data, $form ) {
    if ( ! $this->get_wpforms_transceiver_field_ids( $form_data ) ) {
      return;
    }

    $this->enqueue_tracking_script();
  }

  /**
   * Register Tracking smart tags for WPForms builder dropdowns.
   *
   * @param array $smart_tags Registered smart tags.
   *
   * @return array
   */
  public function register_wpforms_tracking_smart_tags( $smart_tags ) {
    if ( ! is_array( $smart_tags ) ) {
      $smart_tags = [];
    }

    $smart_tags['tracking_all'] = __( 'Tracking: All Values', 'presswell-signal-relay' );

    foreach ( $this->service->get_tracking_keys( PWTSR::ADAPTER_WPFORMS ) as $key ) {
      $smart_tags[ 'tracking_' . $key ] = sprintf(
        /* translators: %s tracking key name. */
        __( 'Tracking: %s', 'presswell-signal-relay' ),
        $key
      );
    }

    return $smart_tags;
  }

  /**
   * Replace custom tracking smart tags.
   *
   * @param mixed       $value            Existing smart tag value.
   * @param string      $tag_name         Smart tag name.
   * @param array       $form_data        Form data.
   * @param array       $fields           Processed fields.
   * @param int|string  $entry_id         Entry id.
   * @param object|null $smart_tag_object Smart tag object.
   * @param string      $context          Context string. WPForms only passes it from 1.9.7.3.
   *
   * @return mixed
   */
  public function replace_wpforms_tracking_smart_tags( $value, $tag_name, $form_data = [], $fields = [], $entry_id = 0, $smart_tag_object = null, $context = '' ) {
    if ( ! is_string( $tag_name ) || 0 !== strpos( $tag_name, 'tracking_' ) ) {
      return $value;
    }

    $pairs = $this->get_wpforms_tracking_pairs( $fields, $form_data );

    if ( 'tracking_all' === $tag_name ) {
      if ( empty( $pairs ) ) {
        return '';
      }

      $lines = [];
      foreach ( $pairs as $key => $pair_value ) {
        $lines[] = $key . ': ' . $pair_value;
      }

      return implode( "\n", $lines );
    }

    $key = substr( $tag_name, strlen( 'tracking_' ) );
    if ( '' === $key ) {
      return '';
    }

    return isset( $pairs[ $key ] ) ? $pairs[ $key ] : '';
  }

  /**
   * Return ids for transceiver fields in a WPForms form config.
   *
   * @param array $form_data Form configuration.
   *
   * @return int[]
   */
  private function get_wpforms_transceiver_field_ids( $form_data ) {
    if ( ! is_array( $form_data ) || empty( $form_data['fields'] ) || ! is_array( $form_data['fields'] ) ) {
      return [];
    }

    $ids = [];
    foreach ( $form_data['fields'] as $field_id => $field ) {
      if ( ! is_array( $field ) ) {
        continue;
      }

      if ( empty( $field['type'] ) || PWTSR::FIELD_TYPE !== $field['type'] ) {
        continue;
      }

      $id = is_numeric( $field_id ) ? absint( $field_id ) : ( ! empty( $field['id'] ) ? absint( $field['id'] ) : 0 );
      if ( $id ) {
        $ids[] = $id;
      }
    }

    return array_values( array_unique( $ids ) );
  }

  /**
   * Tracking pairs from the processed fields, which hold them under 'tracking'.
   *
   * @param array $fields    Processed fields.
   * @param array $form_data Form data.
   *
   * @return array
   */
  private function get_wpforms_tracking_pairs( $fields, $form_data ) {
    $pairs = [];
    if ( ! is_array( $fields ) ) {
      return $pairs;
    }

    foreach ( $this->get_wpforms_transceiver_field_ids( $form_data ) as $field_id ) {
      $tracking = isset( $fields[ $field_id ]['tracking'] ) && is_array( $fields[ $field_id ]['tracking'] ) ? $fields[ $field_id ]['tracking'] : [];

      foreach ( $this->service->get_tracking_keys( PWTSR::ADAPTER_WPFORMS ) as $key ) {
        $clean = isset( $tracking[ $key ] ) ? $this->service->sanitize_tracking_value( $key, $tracking[ $key ] ) : '';
        if ( '' !== $clean ) {
          $pairs[ $key ] = $clean;
        }
      }
    }

    return $pairs;
  }
}
