<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Gravity Forms integration hooks.
 */
trait PWTSR_Gravity_Forms_Trait {

  /**
   * Whether inline visibility styles were enqueued.
   *
   * @var bool
   */
  private $styles_enqueued = false;

  /**
   * Register Gravity Forms hooks. The bootstrap only loads this adapter when Gravity Forms is active.
   */
  protected function construct_gravity_forms_trait() {
    GF_Fields::register( new PWTSR_Gravity_Forms_Field() );

    add_action( 'gform_editor_js_set_default_values', [ $this, 'output_editor_defaults_js' ] );
    add_action( 'admin_enqueue_scripts', [ $this, 'maybe_enqueue_editor_guard_script' ] );
    add_action( 'gform_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ] );
    add_filter( 'gform_pre_render', [ $this, 'enforce_single_tracking_field' ], 5 );
    add_filter( 'gform_pre_validation', [ $this, 'enforce_single_tracking_field' ], 5 );
    add_filter( 'gform_pre_submission_filter', [ $this, 'enforce_single_tracking_field' ], 5 );
    add_filter( 'gform_admin_pre_render', [ $this, 'enforce_single_tracking_field' ], 5 );
    add_filter( 'gform_custom_merge_tags', [ $this, 'register_tracking_merge_tags' ] );
    add_filter( 'gform_replace_merge_tags', [ $this, 'replace_tracking_merge_tags' ], 10, 7 );
  }

  /**
   * Output default settings for the field within the form editor.
   */
  public function output_editor_defaults_js() {
    $keys     = $this->service->get_tracking_keys( PWTSR::ADAPTER_GRAVITY_FORMS );
    $suffixes = PWTSR_Gravity_Forms_Field::get_input_suffixes( count( $keys ) );
    ?>
    case '<?php echo esc_js( PWTSR::FIELD_TYPE ); ?>':
      field.label = '<?php echo esc_js( __( 'Tracking', 'presswell-signal-relay' ) ); ?>';
      field.labelPlacement = 'hidden_label';
      field.description = '<?php echo esc_js( __( 'Captures UTM and click attribution parameters for the current visitor.', 'presswell-signal-relay' ) ); ?>';
      field.inputs = [];
      <?php foreach ( $keys as $index => $key ) : ?>
      field.inputs.push( Object.assign( new Input( field.id + '.<?php echo esc_js( $suffixes[ $index ] ); ?>', '<?php echo esc_js( $key ); ?>' ), { name: '<?php echo esc_js( $key ); ?>' } ) );
      <?php endforeach; ?>
    break;
    <?php
  }

  /**
   * Register and enqueue front-end assets when needed.
   *
   * @param array $form Gravity Forms form array.
   */
  public function maybe_enqueue_assets( $form ) {
    if ( ! $this->form_contains_tracking_field( $form ) ) {
      return;
    }

    $this->enqueue_tracking_script();
    $this->enqueue_visibility_styles();
  }

  /**
   * Output CSS that removes the tracking field from layout flow.
   */
  private function enqueue_visibility_styles() {
    if ( $this->styles_enqueued ) {
      return;
    }

    if ( $this->is_debug_field_mode() ) {
      $this->styles_enqueued = true;
      return;
    }

    wp_register_style( PWTSR::ASSET_HANDLE_STYLE, false, [], PWTSR::VERSION );
    wp_enqueue_style( PWTSR::ASSET_HANDLE_STYLE );

    $css = '.gfield--type-presswell_transceiver,.gfield--type-presswell-transceiver{position:absolute!important;width:1px!important;height:1px!important;padding:0!important;margin:-1px!important;overflow:hidden!important;clip:rect(0,0,0,0)!important;clip-path:inset(50%)!important;white-space:nowrap!important;border:0!important;}';
    wp_add_inline_style( PWTSR::ASSET_HANDLE_STYLE, $css );

    $this->styles_enqueued = true;
  }

  /**
   * Determine if the current form includes the tracking field.
   *
   * @param array $form Gravity Forms form array.
   *
   * @return bool
   */
  private function form_contains_tracking_field( $form ) {
    if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
      return false;
    }

    foreach ( $form['fields'] as $field ) {
      $field_type = $this->extract_field_type( $field );
      if ( $field_type && PWTSR::FIELD_TYPE === $field_type ) {
        return true;
      }
    }

    return false;
  }

  /**
   * Ensure only one tracking field exists per form.
   *
   * @param array $form Current Gravity Forms form array.
   *
   * @return array
   */
  public function enforce_single_tracking_field( $form ) {
    if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
      return $form;
    }

    $filtered = [];
    $found    = false;

    foreach ( $form['fields'] as $field ) {
      $field_type = $this->extract_field_type( $field );

      if ( $field_type && PWTSR::FIELD_TYPE === $field_type ) {
        if ( $found ) {
          continue;
        }

        $found = true;
      }

      $filtered[] = $field;
    }

    if ( count( $filtered ) !== count( $form['fields'] ) ) {
      $form['fields'] = array_values( $filtered );
    }

    return $form;
  }

  /**
   * Enqueue editor guard script on Gravity Forms form editor screens.
   */
  public function maybe_enqueue_editor_guard_script() {
    if ( ! is_admin() ) {
      return;
    }

    $screen_id = '';
    if ( function_exists( 'get_current_screen' ) ) {
      $screen = get_current_screen();
      if ( is_object( $screen ) && isset( $screen->id ) ) {
        $screen_id = (string) $screen->id;
      }
    }

    $page_raw = filter_input( INPUT_GET, 'page', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
    $page     = is_string( $page_raw ) ? sanitize_key( $page_raw ) : '';

    $is_gravity_editor_screen = false !== strpos( $screen_id, 'gf_edit_forms' ) || 'gf_edit_forms' === $page;
    if ( ! $is_gravity_editor_screen ) {
      return;
    }

    wp_enqueue_script(
      PWTSR::ASSET_HANDLE_GRAVITY_EDITOR_SCRIPT,
      plugin_dir_url( Presswell_Tracking_Signal_Relay::PLUGIN_FILE ) . 'assets/js/gravity-editor-guard.js',
      [],
      PWTSR::VERSION,
      true
    );

    $config = wp_json_encode(
      [
        'fieldType' => PWTSR::FIELD_TYPE,
        'title'     => __( 'Tracking', 'presswell-signal-relay' ),
        'warning'   => __( 'Only one Tracking field can be added per form.', 'presswell-signal-relay' ),
      ]
    );

    if ( is_string( $config ) && '' !== $config ) {
      wp_add_inline_script(
        PWTSR::ASSET_HANDLE_GRAVITY_EDITOR_SCRIPT,
        'window.' . PWTSR::JS_OBJECT_GRAVITY_EDITOR . '=' . $config . ';',
        'before'
      );
    }
  }

  /**
   * Extract field type from an object or array field definition.
   *
   * @param mixed $field Gravity Forms field.
   *
   * @return string
   */
  private function extract_field_type( $field ) {
    if ( is_object( $field ) && isset( $field->type ) ) {
      return $field->type;
    }

    if ( is_array( $field ) && isset( $field['type'] ) ) {
      return $field['type'];
    }

    return '';
  }

  /**
   * Register custom merge tags for tracking values.
   *
   * @param array $merge_tags Existing merge tags.
   *
   * @return array
   */
  public function register_tracking_merge_tags( $merge_tags ) {
    if ( ! is_array( $merge_tags ) ) {
      $merge_tags = [];
    }

    $merge_tags[] = [
      'label' => __( 'Tracking: All Values', 'presswell-signal-relay' ),
      'tag'   => '{tracking:all}',
    ];

    foreach ( $this->service->get_tracking_keys( PWTSR::ADAPTER_GRAVITY_FORMS ) as $key ) {
      $merge_tags[] = [
        /* translators: %s: Tracking key name. */
        'label' => sprintf( __( 'Tracking: %s', 'presswell-signal-relay' ), $key ),
        'tag'   => '{tracking:' . $key . '}',
      ];
    }

    return $merge_tags;
  }

  /**
   * Replace custom tracking merge tags with entry values.
   *
   * Supported tags:
   * - {tracking:all}
   * - {tracking:key_name}
   *
   * @param string $text       Text with merge tags.
   * @param array  $form       Current form array.
   * @param array  $entry      Current entry array.
   * @param bool   $url_encode Whether to url encode replacement values.
   * @param bool   $esc_html   Whether to escape HTML in replacement values.
   * @param bool   $nl2br      Whether to convert newlines to br tags.
   * @param string $format     Output format.
   *
   * @return string
   */
  public function replace_tracking_merge_tags( $text, $form, $entry, $url_encode, $esc_html, $nl2br, $format ) {
    if ( ! is_string( $text ) || false === strpos( $text, '{tracking' ) ) {
      return $text;
    }

    $pairs = $this->get_gravity_tracking_pairs_from_entry( $form, $entry );
    if ( empty( $pairs ) ) {
      $text = str_replace( [ '{tracking:all}' ], '', $text );

      return preg_replace( '/\{tracking:([a-z0-9_\-]+)\}/i', '', $text );
    }

    $all_value = implode( ', ', array_map(
      static function( $key, $value ) {
        return $key . '=' . $value;
      },
      array_keys( $pairs ),
      array_values( $pairs )
    ) );

    $text = str_replace( '{tracking:all}', $this->format_merge_tag_value( $all_value, $url_encode, $esc_html, $nl2br, $format ), $text );

    return preg_replace_callback(
      '/\{tracking:([a-z0-9_\-]+)\}/i',
      function( $matches ) use ( $pairs, $url_encode, $esc_html, $nl2br, $format ) {
        $key = isset( $matches[1] ) ? strtolower( (string) $matches[1] ) : '';
        if ( 'all' === $key ) {
          return $matches[0];
        }

        $value = isset( $pairs[ $key ] ) ? $pairs[ $key ] : '';

        return $this->format_merge_tag_value( $value, $url_encode, $esc_html, $nl2br, $format );
      },
      $text
    );
  }

  /**
   * Resolve tracking key/value pairs from the Gravity entry.
   *
   * @param array $form  Current form array.
   * @param array $entry Current entry array.
   *
   * @return array
   */
  private function get_gravity_tracking_pairs_from_entry( $form, $entry ) {
    if ( ! is_array( $form ) || empty( $form['fields'] ) || ! is_array( $form['fields'] ) || ! is_array( $entry ) ) {
      return [];
    }

    $tracking_field = null;
    foreach ( $form['fields'] as $field ) {
      if ( PWTSR::FIELD_TYPE === $this->extract_field_type( $field ) ) {
        $tracking_field = $field;
        break;
      }
    }

    if ( null === $tracking_field ) {
      return [];
    }

    if ( is_array( $tracking_field ) && class_exists( 'GF_Fields' ) ) {
      $tracking_field = GF_Fields::create( $tracking_field );
    }

    if ( ! $tracking_field instanceof PWTSR_Gravity_Forms_Field ) {
      return [];
    }

    return $tracking_field->get_entry_tracking_pairs( $entry );
  }

  /**
   * Apply Gravity merge-tag format options to a value.
   *
   * @param string $value      Raw value.
   * @param bool   $url_encode Whether to url encode value.
   * @param bool   $esc_html   Whether to escape HTML.
   * @param bool   $nl2br      Whether to convert newlines to br.
   * @param string $format     Output format.
   *
   * @return string
   */
  private function format_merge_tag_value( $value, $url_encode, $esc_html, $nl2br, $format ) {
    $value = (string) $value;

    if ( $url_encode ) {
      $value = rawurlencode( $value );
    }

    // Gravity Forms escapes its own field merge tags for HTML output whenever the format is
    // html, even when $esc_html is false (as in HTML notifications), so match that.
    if ( $esc_html || 'html' === $format ) {
      $value = esc_html( $value );
    }

    if ( $nl2br ) {
      $value = nl2br( $value );
    }

    return $value;
  }
}
