<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

use FluentForm\App\Services\FormBuilder\BaseFieldManager;

if ( class_exists( 'FluentForm\\App\\Services\\FormBuilder\\BaseFieldManager' ) && ! class_exists( 'PWTSR_Fluent_Forms_Field' ) ) {
  /**
   * Fluent Forms custom field that renders Presswell transceiver inputs.
   */
  class PWTSR_Fluent_Forms_Field extends BaseFieldManager {

    /**
     * Register Fluent Forms custom field metadata.
     */
    public function __construct() {
      parent::__construct(
        PWTSR::FIELD_TYPE,
        __( 'Tracking', 'presswell-signal-relay' ),
        [ 'tracking', 'utm', 'attribution' ],
        'advanced'
      );
    }

    /**
     * Component schema for Fluent Forms builder.
     *
     * @return array
     */
    public function getComponent() {
      return [
        'index'      => 30,
        'element'    => $this->key,
        'attributes' => [
          'name'  => PWTSR::FIELD_NAME,
          'type'  => 'hidden',
          'value' => '',
        ],
        'settings'   => [
          'label'                 => __( 'Tracking', 'presswell-signal-relay' ),
          'html_codes'            => '<p><strong>' . esc_html__( 'Tracking', 'presswell-signal-relay' ) . '</strong></p><p>' . esc_html__( 'Captures UTM and click attribution parameters for the current visitor.', 'presswell-signal-relay' ) . '</p>',
          'container_class'       => '',
          'conditional_logics'    => [],
          'name'                  => PWTSR::FIELD_NAME,
        ],
        'editor_options' => [
          'title'      => __( 'Tracking', 'presswell-signal-relay' ),
          'icon_class' => 'pwtsr-radar-icon',
          'template'   => 'customHTML',
        ],
      ];
    }

    /**
     * Keep editor settings focused for this hidden utility field.
     *
     * @return string[]
     */
    public function getGeneralEditorElements() {
      return [
        'label',
      ];
    }

    /**
     * Keep editor settings focused for this hidden utility field.
     *
     * @return string[]
     */
    public function getAdvancedEditorElements() {
      // No 'name': the adapter reads the field's data under its fixed name.
      return [
        'container_class',
        'conditional_logics',
      ];
    }

    /**
     * Render transceiver hidden inputs for Fluent Forms frontend.
     *
     * @param array  $element Field config.
     * @param object $form    Fluent form model.
     */
    public function render( $element, $form ) {
      if ( is_admin() ) {
        return;
      }

      $debug_mode = PWTSR_Transceiver_Markup::is_debug_mode();
      $inputs     = '';

      foreach ( presswell_tracking_signal_relay()->get_service()->get_tracking_keys( PWTSR::ADAPTER_FLUENT_FORMS ) as $key ) {
        $inputs .= PWTSR_Transceiver_Markup::render_input(
          $key,
          PWTSR::FIELD_NAME . '[' . $key . ']',
          'pwtsr-fluent-' . sanitize_html_class( $key ),
          $debug_mode
        );
      }

      echo wp_kses( PWTSR_Transceiver_Markup::render( PWTSR::ADAPTER_FLUENT_FORMS, $inputs, $debug_mode ), PWTSR_Transceiver_Markup::get_wrapper_allowed_html() );
    }
  }
}
