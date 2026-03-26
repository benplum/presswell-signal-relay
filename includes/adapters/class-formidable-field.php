<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( class_exists( 'FrmFieldHidden' ) && ! class_exists( 'PWTSR_Formidable_Field' ) ) {
  /**
   * Formidable custom field type for Presswell tracking signals.
   */
  class PWTSR_Formidable_Field extends FrmFieldHidden {

    /**
     * Field type slug.
     *
     * @var string
     */
    protected $type = PWTSR::FIELD_TYPE;

    /**
     * Keep custom HTML enabled so Formidable processes the [input] shortcode.
     *
     * @var bool
     */
    protected $has_html = true;

    /**
     * Use a minimal template that only outputs generated hidden inputs.
     *
     * @return string
     */
    public function default_html() {
      return '[input]';
    }

    /**
     * Render builder preview for the custom Tracking field.
     *
     * @return string
     */
    protected function include_form_builder_file() {
      return plugin_dir_path( Presswell_Tracking_Signal_Relay::PLUGIN_FILE ) . 'includes/views/formidable-field-builder.php';
    }

    /**
     * Render transceiver hidden inputs for each tracking key.
     *
     * @param array $args           Field render args.
     * @param array $shortcode_atts Shortcode attributes.
     *
     * @return string
     */
    public function front_field_input( $args, $shortcode_atts ) {
      $field_id = isset( $args['field']['id'] ) ? absint( $args['field']['id'] ) : absint( $this->get_field_column( 'id' ) );
      if ( ! $field_id ) {
        return '';
      }

      $inputs = [];
      // Keep Formidable's field key present, but don't place tracking payload in item_meta before spam validation runs.
      $inputs[] = sprintf(
        '<input type="hidden" name="item_meta[%1$d]" value="" />',
        absint( $field_id )
      );

      foreach ( $this->get_tracking_keys() as $key ) {
        $inputs[] = $this->render_transceiver_input_markup(
          $key,
          'pwtsr_tracking[' . $field_id . '][' . $key . ']',
          'presswell-formidable-' . $field_id . '-' . sanitize_html_class( $key )
        );
      }

      if ( empty( $inputs ) ) {
        return '';
      }

      return PWTSR_Transceiver_Markup::render_wrapper(
        'presswell-transceiver presswell-formidable-transceiver',
        PWTSR::ADAPTER_FORMIDABLE,
        implode( '', $inputs ),
        $this->is_debug_field_mode()
      );
    }

    /**
     * Resolve plugin tracking service.
     *
     * @return PWTSR_Tracking_Service
     */
    private function get_service() {
      if ( function_exists( 'presswell_tracking_signal_relay' ) ) {
        $plugin = presswell_tracking_signal_relay();
        if ( $plugin && method_exists( $plugin, 'get_service' ) ) {
          $service = $plugin->get_service();
          if ( $service instanceof PWTSR_Tracking_Service ) {
            return $service;
          }
        }
      }

      return new PWTSR_Tracking_Service();
    }

    /**
     * Resolve tracking keys for Formidable context.
     *
     * @return string[]
     */
    private function get_tracking_keys() {
      return $this->get_service()->get_tracking_keys( PWTSR::ADAPTER_FORMIDABLE );
    }

    /**
     * Determine whether debug mode is active.
     *
     * @return bool
     */
    private function is_debug_field_mode() {
      if ( function_exists( 'presswell_tracking_signal_relay' ) ) {
        $plugin = presswell_tracking_signal_relay();
        if ( $plugin && method_exists( $plugin, 'should_show_debug_styles' ) ) {
          return (bool) $plugin->should_show_debug_styles();
        }
      }

      return false;
    }

    /**
     * Render per-key transceiver input markup.
     *
     * @param string $key   Tracking key.
     * @param string $name  Input name.
     * @param string $id    Input id.
     * @param string $value Input value.
     *
     * @return string
     */
    private function render_transceiver_input_markup( $key, $name, $id, $value = '' ) {
      if ( $this->is_debug_field_mode() ) {
        return sprintf(
          '<div class="presswell-debug-field-row"><label class="presswell-debug-field-label" for="%1$s">%2$s</label><input type="text" id="%1$s" name="%3$s" value="%4$s" readonly="readonly" data-presswell-transceiver="%5$s" /></div>',
          esc_attr( $id ),
          esc_html( $key ),
          esc_attr( $name ),
          esc_attr( $value ),
          esc_attr( $key )
        );
      }

      return sprintf(
        '<input type="hidden" id="%1$s" name="%2$s" value="%3$s" data-presswell-transceiver="%4$s" />',
        esc_attr( $id ),
        esc_attr( $name ),
        esc_attr( $value ),
        esc_attr( $key )
      );
    }
  }
}
