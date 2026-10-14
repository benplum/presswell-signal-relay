<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'PWTSR_WPForms_Field' ) ) {
  /**
   * WPForms compound field that captures tracking values as explicit subinputs.
   */
  class PWTSR_WPForms_Field extends WPForms_Field {

    /**
     * Initialize field metadata.
     */
    public function init() {
      $this->name     = esc_html__( 'Tracking', 'presswell-signal-relay' );
      $this->keywords = esc_html__( 'tracking, utm, attribution, campaign', 'presswell-signal-relay' );
      $this->type     = PWTSR::FIELD_TYPE;
      $this->icon     = 'pwtsr-radar-icon';
      $this->order    = 310;
      $this->group    = 'fancy';

      $this->default_settings = [
        'label_hide' => '1',
      ];

      add_filter( 'wpforms_field_properties_' . $this->type, [ $this, 'field_properties' ], 10, 3 );
    }

    /**
     * Field options panel inside the builder.
     *
     * @param array $field Field information.
     */
    public function field_options( $field ) {
      $this->field_option(
        'basic-options',
        $field,
        [
          'markup' => 'open',
        ]
      );

      $this->field_option( 'label', $field );

      $this->field_option(
        'basic-options',
        $field,
        [
          'markup' => 'close',
        ]
      );

      $this->field_option(
        'advanced-options',
        $field,
        [
          'markup' => 'open',
        ]
      );

      $this->field_option( 'css', $field );
      $this->field_option( 'label_hide', $field );

      $this->field_option(
        'advanced-options',
        $field,
        [
          'markup' => 'close',
        ]
      );
    }

    /**
     * Builder preview output.
     *
     * @param array $field Field information.
     */
    public function field_preview( $field ) {
      $this->field_preview_option( 'label', $field );

      echo '<p class="wpforms-description">' . esc_html__( 'Captures UTM and click attribution parameters for the current visitor.', 'presswell-signal-relay' ) . '</p>';
    }

    /**
     * Build subinput properties for each tracking key.
     *
     * @param array $properties Field properties.
     * @param array $field      Field configuration.
     * @param array $form_data  Form configuration.
     *
     * @return array
     */
    public function field_properties( $properties, $field, $form_data ) {
      $properties = (array) $properties;

      if ( empty( $field['id'] ) || empty( $form_data['id'] ) ) {
        return $properties;
      }

      $form_id  = absint( $form_data['id'] );
      $field_id = wpforms_validate_field_id( $field['id'] );

      $properties['inputs'] = [];

      foreach ( $this->get_tracking_keys() as $key ) {
        $properties['inputs'][ $key ] = [
          'attr' => [
            'name'  => "wpforms[fields][{$field_id}][{$key}]",
            'value' => '',
          ],
          'id'   => "wpforms-{$form_id}-field_{$field_id}-{$key}",
        ];
      }

      return $properties;
    }

    /**
     * Field output on the frontend.
     *
     * @param array $field      Field configuration.
     * @param array $deprecated Deprecated parameter.
     * @param array $form_data  Form configuration.
     */
    public function field_display( $field, $deprecated, $form_data ) {
      if ( is_admin() || empty( $field['properties']['inputs'] ) ) {
        return;
      }

      $debug_mode = PWTSR_Transceiver_Markup::is_debug_mode();
      $inputs     = '';

      foreach ( $field['properties']['inputs'] as $key => $input ) {
        $inputs .= PWTSR_Transceiver_Markup::render_input( (string) $key, (string) $input['attr']['name'], (string) $input['id'], $debug_mode );
      }

      echo wp_kses( PWTSR_Transceiver_Markup::render( PWTSR::ADAPTER_WPFORMS, $inputs, $debug_mode ), PWTSR_Transceiver_Markup::get_wrapper_allowed_html() );
    }

    /**
     * Format and sanitize value for entry storage.
     *
     * Stores a readable "key: value" summary as the entry value, plus the cleaned pairs
     * under 'tracking' for smart tags.
     *
     * @param int   $field_id     Field id.
     * @param mixed $field_submit Submitted field value.
     * @param array $form_data    Form settings.
     */
    public function format( $field_id, $field_submit, $form_data ) {
      $field_id = wpforms_validate_field_id( $field_id );
      $name     = isset( $form_data['fields'][ $field_id ]['label'] ) ? $form_data['fields'][ $field_id ]['label'] : '';
      $service  = presswell_tracking_signal_relay()->get_service();

      $pairs = [];
      $lines = [];
      foreach ( $this->get_tracking_keys() as $key ) {
        $raw   = is_array( $field_submit ) && isset( $field_submit[ $key ] ) ? $field_submit[ $key ] : '';
        $clean = $service->sanitize_tracking_value( $key, $raw );

        if ( '' !== $clean ) {
          $pairs[ $key ] = $clean;
          $lines[]       = $key . ': ' . $clean;
        }
      }

      wpforms()->obj( 'process' )->fields[ $field_id ] = [
        'name'     => sanitize_text_field( $name ),
        'value'    => implode( "\n", $lines ),
        'id'       => $field_id,
        'type'     => $this->type,
        'tracking' => $pairs,
      ];
    }

    /**
     * Tracking keys for this field.
     *
     * @return string[]
     */
    private function get_tracking_keys() {
      return presswell_tracking_signal_relay()->get_service()->get_tracking_keys( PWTSR::ADAPTER_WPFORMS );
    }
  }
}
