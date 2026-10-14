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

      $debug_mode = PWTSR_Transceiver_Markup::is_debug_mode();

      // Keep Formidable's field key present, but don't place tracking payload in item_meta before spam validation runs.
      $inputs = sprintf( '<input type="hidden" name="item_meta[%1$d]" value="" />', $field_id );

      foreach ( presswell_tracking_signal_relay()->get_service()->get_tracking_keys( PWTSR::ADAPTER_FORMIDABLE ) as $key ) {
        $inputs .= PWTSR_Transceiver_Markup::render_input(
          $key,
          PWTSR::FIELD_NAME . '[' . $field_id . '][' . $key . ']',
          'pwtsr-formidable-' . $field_id . '-' . sanitize_html_class( $key ),
          $debug_mode
        );
      }

      return PWTSR_Transceiver_Markup::render( PWTSR::ADAPTER_FORMIDABLE, $inputs, $debug_mode );
    }
  }
}
