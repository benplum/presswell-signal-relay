<?php
/**
 * Gravity Forms field that stores session tracking parameters.
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( class_exists( 'GF_Field' ) && ! class_exists( 'PWTSR_Gravity_Forms_Field' ) ) {

  /**
   * Gravity Forms field implementation for Tracking Signal Relay.
   */
  class PWTSR_Gravity_Forms_Field extends GF_Field {

    /**
     * Field type identifier registered with Gravity Forms.
     *
     * @var string
     */
    public $type = PWTSR::FIELD_TYPE;

    /**
     * Hide the field label by default.
     *
     * @var string
     */
    public $labelPlacement = 'hidden_label';

    /**
     * Resolve the shared tracking service.
     *
     * @return PWTSR_Tracking_Service
     */
    private function service() {
      return presswell_tracking_signal_relay()->get_service();
    }

    /**
     * Title displayed within the form editor panel.
     *
     * @return string
     */
    public function get_form_editor_field_title() {
      return esc_html__( 'Tracking', 'presswell-signal-relay' );
    }

    /**
     * Provide a custom radar icon for the form editor button.
     *
     * @return string
     */
    public function get_form_editor_field_icon() {
      return plugin_dir_url( Presswell_Tracking_Signal_Relay::PLUGIN_FILE ) . 'assets/svg/radar.svg';
    }

    /**
     * Settings available when editing the field in the form editor.
     *
     * @return string[]
     */
    public function get_form_editor_field_settings() {
      return [
        'label_setting',
        'description_setting',
        'css_class_setting',
      ];
    }

    /**
     * Button registration details for the form editor panel.
     *
     * @return array
     */
    public function get_form_editor_button() {
      return [
        'group' => 'advanced_fields',
        'text'  => esc_html__( 'Tracking', 'presswell-signal-relay' ),
      ];
    }

    /**
     * Disable conditional logic configuration for this field.
     *
     * @return bool
     */
    public function is_conditional_logic_supported() {
      return false;
    }

    /**
     * Define the individual inputs saved alongside entries.
     *
     * @return array
     */
    public function get_inputs() {
      $inputs   = [];
      $suffixes = self::get_input_suffixes( count( $this->get_current_keys() ) );

      foreach ( $this->get_current_keys() as $index => $key ) {
        $inputs[] = $this->build_input( $suffixes[ $index ], $key );
      }

      return $inputs;
    }

    /**
     * Inputs saved with entries: the ones stored on the field, plus any key added since.
     *
     * Values are matched to keys by each input's stored key, never by position, so adding,
     * removing, or reordering tracking keys doesn't shift existing entries.
     *
     * @return array
     */
    public function get_entry_inputs() {
      $inputs = is_array( $this->inputs ) ? array_values( array_filter( $this->inputs, 'is_array' ) ) : [];
      $known  = [];
      $last   = 0;

      foreach ( $inputs as $input ) {
        $key = $this->get_stored_input_key( $input );
        if ( '' !== $key ) {
          $known[ $key ] = true;
        }

        $parts = explode( '.', isset( $input['id'] ) ? (string) $input['id'] : '' );
        $last  = max( $last, isset( $parts[1] ) ? absint( $parts[1] ) : 0 );
      }

      foreach ( $this->get_current_keys() as $key ) {
        if ( isset( $known[ $key ] ) ) {
          continue;
        }

        $last     = self::next_input_suffix( $last );
        $inputs[] = $this->build_input( $last, $key );
      }

      $this->inputs = $inputs;

      return $this->inputs;
    }

    /**
     * Input number suffixes for a field with the given number of inputs.
     *
     * Gravity Forms compares input IDs as numbers in places, so 5.10 would collide with 5.1;
     * like its checkbox field, skip every multiple of ten.
     *
     * @param int $count Number of inputs.
     *
     * @return int[]
     */
    public static function get_input_suffixes( $count ) {
      $suffixes = [];
      $suffix   = 0;

      for ( $i = 0; $i < $count; $i++ ) {
        $suffix     = self::next_input_suffix( $suffix );
        $suffixes[] = $suffix;
      }

      return $suffixes;
    }

    /**
     * Next usable input suffix after the given one.
     *
     * @param int $suffix Current suffix.
     *
     * @return int
     */
    private static function next_input_suffix( $suffix ) {
      $suffix++;
      if ( 0 === $suffix % 10 ) {
        $suffix++;
      }

      return $suffix;
    }

    /**
     * Build one input definition.
     *
     * @param int    $suffix Input number suffix.
     * @param string $key    Tracking key.
     *
     * @return array
     */
    private function build_input( $suffix, $key ) {
      return [
        'id'       => sprintf( '%d.%d', $this->id, $suffix ),
        'label'    => $key,
        'name'     => $key,
        'isHidden' => true,
      ];
    }

    /**
     * Tracking keys currently configured.
     *
     * @return string[]
     */
    private function get_current_keys() {
      return $this->service()->get_tracking_keys( PWTSR::ADAPTER_GRAVITY_FORMS );
    }

    /**
     * Read the tracking key an input stores.
     *
     * @param array $input Input definition.
     *
     * @return string
     */
    private function get_stored_input_key( $input ) {
      // Inputs added by older editor code carry the key as their label with an empty name.
      foreach ( [ 'name', 'label' ] as $property ) {
        if ( ! empty( $input[ $property ] ) && is_string( $input[ $property ] ) ) {
          return sanitize_key( $input[ $property ] );
        }
      }

      return '';
    }

    /**
     * Sanitized key/value pairs captured on an entry, skipping empty values.
     *
     * @param array $entry Entry.
     *
     * @return array
     */
    public function get_entry_tracking_pairs( $entry ) {
      return $this->filter_empty_pairs( $this->get_tracking_value_pairs( $entry ) );
    }

    /**
     * Render hidden inputs that will be populated by the tracking script.
     *
     * @param array        $form  Current form object.
     * @param string|array $value Current value.
     * @param array|null   $entry Entry data when in admin context.
     *
     * @return string
     */
    public function get_field_input( $form, $value = '', $entry = null ) {
      $form_id    = absint( rgar( $form, 'id' ) );
      $inputs     = $this->get_entry_inputs();
      $input_tag  = [];
      $debug_mode = $this->is_debug_field_mode();
      $keys       = $this->get_current_keys();

      if ( empty( $inputs ) ) {
        return '';
      }

      foreach ( $inputs as $input ) {
        $key_name = $this->get_stored_input_key( $input );

        // Inputs left over from a removed key stay on old entries but aren't captured anymore.
        if ( '' === $key_name || ! in_array( $key_name, $keys, true ) ) {
          continue;
        }

        $input_id   = (string) $input['id'];
        $custom_id  = sprintf( 'input_%d_%s', $form_id, str_replace( '.', '_', $input_id ) );
        $field_name = 'input_' . str_replace( '.', '_', $input_id );
        $current    = '';

        if ( is_array( $value ) ) {
          $current = rgar( $value, $input_id );
        } elseif ( is_scalar( $value ) ) {
          $current = $value;
        }

        if ( is_array( $entry ) ) {
          $entry_value = rgar( $entry, $input_id );
          if ( null !== $entry_value ) {
            $current = $entry_value;
          }
        }

        $current = $this->service()->sanitize_tracking_value( $key_name, $current );

        $input_tag[] = PWTSR_Transceiver_Markup::render_input( $key_name, $field_name, $custom_id, $debug_mode, $current );
      }

      return PWTSR_Transceiver_Markup::render(
        PWTSR::ADAPTER_GRAVITY_FORMS,
        implode( '', $input_tag ),
        $debug_mode,
        'pwtsr-transceiver-field ginput_container' . ( $debug_mode ? ' gform-theme__disable-reset' : '' )
      );
    }

    /**
     * Whether debug field mode is enabled for current user/request.
     *
     * @return bool
     */
    private function is_debug_field_mode() {
      // The form editor previews the field itself, never the debug panel.
      return ! ( class_exists( 'GFCommon' ) && GFCommon::is_form_editor() ) && PWTSR_Transceiver_Markup::is_debug_mode();
    }

    /**
     * Suppress descriptive text on public forms while preserving editor/admin output.
     *
     * @param string $value   Current value.
     * @param string $lead_id Entry ID when loading existing entries.
     * @param int    $form_id Form ID.
     *
     * @return string
     */
    public function get_field_content( $value, $lead_id = 0, $form_id = 0 ) {
      if ( GFCommon::is_form_editor() ) {
        $content  = parent::get_field_content( $value, $lead_id, $form_id );
        $icon_url = esc_url( plugin_dir_url( Presswell_Tracking_Signal_Relay::PLUGIN_FILE ) . 'assets/svg/radar.svg' );
        $label    = esc_html__( 'Tracking', 'presswell-signal-relay' );

        $badge = sprintf(
          '<div class="pwtsr-transceiver-editor-badge" style="display:flex;align-items:center;gap:6px;margin-bottom:6px;font-weight:600;"><img src="%1$s" alt="%2$s" width="20" height="20" style="display:block;" /><span>%2$s</span></div>',
          $icon_url,
          $label
        );

        return sprintf( '<div class="pwtsr-transceiver-editor-preview">%1$s%2$s</div>', $badge, $content );
      }

      if ( GFCommon::is_entry_detail() ) {
        return parent::get_field_content( $value, $lead_id, $form_id );
      }

      $original_label       = $this->label;
      $original_description = $this->description;

      $this->label       = __( 'Signals', 'presswell-signal-relay' );
      $this->description = '';

      $content = parent::get_field_content( $value, $lead_id, $form_id );

      $this->label       = $original_label;
      $this->description = $original_description;

      return $content;
    }

    /**
     * Display all stored tracking pairs inside the entry detail view.
     *
     * @param mixed  $value    Entry value.
     * @param string $currency Entry currency.
     * @param bool   $use_text Return as text.
     * @param string $format   Output format.
     * @param string $media    Output media.
     *
     * @return string
     */
    public function get_value_entry_detail( $value, $currency = '', $use_text = false, $format = 'html', $media = 'screen' ) {
      $pairs = $this->filter_empty_pairs( $this->get_tracking_value_pairs( $value ) );

      if ( empty( $pairs ) ) {
        $entry_context = $this->get_entry_context_values();
        if ( ! empty( $entry_context ) ) {
          $pairs = $this->filter_empty_pairs( $this->get_tracking_value_pairs( $entry_context ) );
        }
      }

      if ( empty( $pairs ) ) {
        return '';
      }

      if ( 'text' === $format || 'url' === $format ) {
        $separator = 'text' === $format ? "\n" : ', ';
        return implode( $separator, $this->format_pairs_as_text( $pairs ) );
      }

      $items = [];
      foreach ( $pairs as $key => $val ) {
        $items[] = sprintf( '<li><strong>%s:</strong> %s</li>', esc_html( $key ), esc_html( $val ) );
      }

      return sprintf( '<ul class="pwtsr-utm-entry-detail">%s</ul>', implode( '', $items ) );
    }

    /**
     * Surface a concise summary on the entries list table.
     *
     * @param mixed $value   Entry value.
     * @param array $entry   Entry payload.
     * @param mixed $field_id Field id.
     * @param mixed $columns Columns array.
     * @param mixed $form    Form payload.
     *
     * @return string
     */
    public function get_value_entry_list( $value, $entry, $field_id, $columns, $form ) {
      // A column for one input (12.3) shows that key's value; the whole-field column a short summary.
      if ( false !== strpos( (string) $field_id, '.' ) ) {
        $key = $this->get_input_key( 'input_' . str_replace( '.', '_', (string) $field_id ) );

        return '' === $key ? '' : esc_html( $this->service()->sanitize_tracking_value( $key, rgar( $entry, (string) $field_id ) ) );
      }

      $pairs = $this->get_entry_tracking_pairs( $entry );
      if ( empty( $pairs ) ) {
        return '';
      }

      return esc_html( implode( ', ', array_slice( $this->format_pairs_as_text( $pairs ), 0, 3 ) ) );
    }

    /**
     * Convert stored entry values into key/value pairs.
     *
     * @param mixed $raw Raw entry value passed by Gravity Forms.
     *
     * @return array
     */
    private function get_tracking_value_pairs( $raw ) {
      $source = $this->normalize_value_source( $raw );
      $pairs  = [];

      foreach ( $this->get_entry_inputs() as $input ) {
        $key = $this->get_stored_input_key( $input );
        if ( '' === $key || ! isset( $input['id'] ) ) {
          continue;
        }

        $value = rgar( $source, (string) $input['id'] );
        if ( null === $value && isset( $source[ $key ] ) ) {
          $value = $source[ $key ];
        }

        $pairs[ $key ] = $this->service()->sanitize_tracking_value( $key, $value );
      }

      return $pairs;
    }

    /**
     * Attempt to load full entry array in entry detail context.
     *
     * @return array
     */
    private function get_entry_context_values() {
      if ( isset( $this->entry ) && is_array( $this->entry ) ) {
        return $this->entry;
      }

      if ( ! GFCommon::is_entry_detail() || ! class_exists( 'GFAPI' ) ) {
        return [];
      }

      $entry_id = absint( rgget( 'lid' ) );
      if ( ! $entry_id ) {
        $entry_id = absint( rgget( 'entry' ) );
      }

      if ( ! $entry_id ) {
        return [];
      }

      $entry = GFAPI::get_entry( $entry_id );
      if ( is_wp_error( $entry ) || ! is_array( $entry ) ) {
        return [];
      }

      return $entry;
    }

    /**
     * Normalize array or JSON-encoded values into arrays.
     *
     * @param mixed $raw Entry value or JSON string.
     *
     * @return array
     */
    private function normalize_value_source( $raw ) {
      if ( is_array( $raw ) ) {
        return $raw;
      }

      // Never unserialize: entry strings come from visitors.
      if ( is_string( $raw ) ) {
        $decoded = json_decode( $raw, true );
        if ( is_array( $decoded ) ) {
          return $decoded;
        }
      }

      return [];
    }

    /**
     * Remove empty values from a key/value map.
     *
     * @param array $pairs Key/value map.
     *
     * @return array
     */
    private function filter_empty_pairs( $pairs ) {
      return array_filter(
        $pairs,
        static function ( $value ) {
          return $value !== '' && $value !== null;
        }
      );
    }

    /**
     * Format pairs as key/value strings.
     *
     * @param array $pairs Key/value map.
     *
     * @return array
     */
    private function format_pairs_as_text( $pairs ) {
      $lines = [];
      foreach ( $pairs as $key => $val ) {
        $lines[] = sprintf( '%s: %s', $key, $val );
      }

      return $lines;
    }

    /**
     * Sanitize an input value before Gravity Forms saves it to the entry (Gravity Forms 3.0+).
     *
     * @param mixed  $value          Submitted input value.
     * @param array  $form           Current form.
     * @param string $input_name     Posted input name, e.g. input_5_2.
     * @param int    $entry_id       Entry ID.
     * @param array  $entry          Entry being saved.
     * @param string $repeater_index Repeater index when nested in a repeater.
     *
     * @return string
     */
    public function get_value_save_input( $value, $form, $input_name, $entry_id, $entry, $repeater_index = '' ) {
      return $this->sanitize_input_value( $value, $input_name );
    }

    /**
     * Sanitize an input value before Gravity Forms saves it to the entry (Gravity Forms 2.x).
     *
     * @param mixed  $value      Submitted input value.
     * @param array  $form       Current form.
     * @param string $input_name Posted input name, e.g. input_5_2.
     * @param int    $lead_id    Entry ID.
     * @param array  $lead       Entry being saved.
     *
     * @return string
     */
    public function get_value_save_entry( $value, $form, $input_name, $lead_id, $lead ) {
      return $this->sanitize_input_value( $value, $input_name );
    }

    /**
     * Sanitize a submitted value for the tracking key its input stores.
     *
     * Runs for every submission path (front end, AJAX, GFAPI::submit_form), so values are
     * cleaned regardless of whether the form carries a submission nonce.
     *
     * @param mixed  $value      Submitted input value.
     * @param string $input_name Posted input name, e.g. input_5_2.
     *
     * @return string
     */
    private function sanitize_input_value( $value, $input_name ) {
      if ( ! is_scalar( $value ) ) {
        return '';
      }

      $key = $this->get_input_key( (string) $input_name );
      if ( '' === $key ) {
        return '';
      }

      return $this->service()->sanitize_tracking_value( $key, $value );
    }

    /**
     * Resolve the tracking key stored by a posted input name.
     *
     * @param string $input_name Posted input name, e.g. input_5_2.
     *
     * @return string
     */
    private function get_input_key( $input_name ) {
      if ( ! preg_match( '/_(\d+)$/', $input_name, $matches ) ) {
        return '';
      }

      $input_id = sprintf( '%d.%d', $this->id, (int) $matches[1] );

      foreach ( $this->get_entry_inputs() as $input ) {
        if ( isset( $input['id'] ) && (string) $input['id'] === $input_id ) {
          return $this->get_stored_input_key( $input );
        }
      }

      return '';
    }

    /**
     * Ensure Gravity Forms stores array data for each input.
     *
     * @return bool
     */
    public function is_value_submission_array() {
      return true;
    }
  }
}
