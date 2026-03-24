<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Gravity Forms integration hooks.
 */
trait PWSL_Gravity_Forms_Trait {

  /**
   * Initialize Gravity Forms hooks when available.
   */
  public function maybe_bootstrap_gravity_forms() {
    if ( ! class_exists( 'GFForms' ) ) {
      return;
    }

    GF_Fields::register( new PWSL_Gravity_Forms_Field() );

    add_action( 'gform_editor_js_set_default_values', [ $this, 'output_editor_defaults_js' ] );
    add_action( 'gform_editor_js', [ $this, 'output_editor_guard_js' ] );
    add_action( 'gform_enqueue_scripts', [ $this, 'maybe_enqueue_assets' ], 10, 2 );
    add_filter( 'gform_pre_form_editor_save', [ $this, 'enforce_single_tracking_field' ] );
    add_filter( 'gform_pre_render', [ $this, 'enforce_single_tracking_field' ], 5 );
    add_filter( 'gform_pre_validation', [ $this, 'enforce_single_tracking_field' ], 5 );
    add_filter( 'gform_pre_submission_filter', [ $this, 'enforce_single_tracking_field' ], 5 );
    add_filter( 'gform_pre_submission_filter', [ $this, 'sanitize_tracking_submission_values' ], 10 );
    add_filter( 'gform_admin_pre_render', [ $this, 'enforce_single_tracking_field' ], 5 );
  }

  /**
   * Output default settings for the field within the form editor.
   */
  public function output_editor_defaults_js() {
    $keys = $this->service->get_tracking_keys( 'gravityforms' );
    ?>
    case 'presswell_transceiver':
      field.label = '<?php echo esc_js( __( 'Tracking', PWSL::TEXT_DOMAIN ) ); ?>';
      field.labelPlacement = 'hidden_label';
      field.description = '<?php echo esc_js( __( 'Captures UTM and click attribution parameters for the current visitor.', PWSL::TEXT_DOMAIN ) ); ?>';
      field.inputs = [];
      <?php foreach ( $keys as $index => $key ) : ?>
      field.inputs.push( new Input( field.id + '.<?php echo esc_js( $index + 1 ); ?>', '<?php echo esc_js( $key ); ?>', '<?php echo esc_js( $key ); ?>' ) );
      <?php endforeach; ?>
    break;
    <?php
  }

  /**
   * Register and enqueue front-end assets when needed.
   *
   * @param array $form Gravity Forms form array.
   */
  public function maybe_enqueue_assets( $form, $is_ajax = false ) {
    if ( ! $this->form_contains_tracking_field( $form ) ) {
      return;
    }

    $this->enqueue_tracking_script( PWSL::ADAPTER_GRAVITY_FORMS );
    $this->enqueue_visibility_styles();
  }

  /**
   * Output CSS that removes the tracking field from layout flow.
   */
  private function enqueue_visibility_styles() {
    if ( $this->styles_enqueued ) {
      return;
    }

    wp_register_style( PWSL::ASSET_HANDLE_STYLE, false, [], PWSL::VERSION );
    wp_enqueue_style( PWSL::ASSET_HANDLE_STYLE );

    $css = '.gfield--type-' . PWSL::FIELD_TYPE . '{position:fixed!important;height:0!important;width:0!important;overflow:hidden!important;pointer-events:none!important;}';
    wp_add_inline_style( PWSL::ASSET_HANDLE_STYLE, $css );

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
      if ( $field_type && PWSL::FIELD_TYPE === $field_type ) {
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

      if ( $field_type && PWSL::FIELD_TYPE === $field_type ) {
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
   * Sanitize posted tracking values before entry save.
   *
   * @param array $form Current Gravity Forms form array.
   *
   * @return array
   */
  public function sanitize_tracking_submission_values( $form ) {
    if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) || empty( $_POST ) || ! is_array( $_POST ) ) {
      return $form;
    }

    foreach ( $form['fields'] as $field ) {
      $field_type = $this->extract_field_type( $field );
      if ( PWSL::FIELD_TYPE !== $field_type ) {
        continue;
      }

      $field_id = $this->extract_field_id( $field );
      if ( ! $field_id ) {
        continue;
      }

      $index = 1;
      foreach ( $this->service->get_tracking_keys( 'gravityforms' ) as $key ) {
        $posted_key = sprintf( 'input_%d_%d', $field_id, $index );
        if ( ! isset( $_POST[ $posted_key ] ) ) {
          $index++;
          continue;
        }

        $raw = wp_unslash( $_POST[ $posted_key ] );
        if ( is_array( $raw ) ) {
          unset( $_POST[ $posted_key ] );
          $index++;
          continue;
        }

        $_POST[ $posted_key ] = $this->service->sanitize_tracking_value( $key, $raw );
        $index++;
      }
    }

    return $form;
  }

  /**
   * Block editors from inserting multiple tracking fields.
   */
  public function output_editor_guard_js() {
    ?>
    <script type="text/javascript">
      (function (window, document) {
        var slug = '<?php echo esc_js( PWSL::FIELD_TYPE ); ?>';
        var warning = '<?php echo esc_js( __( 'Only one Tracking field can be added per form.', PWSL::TEXT_DOMAIN ) ); ?>';

        function guardSingleField() {
          if (typeof window.StartAddField !== 'function' || window.StartAddField._presswellTransceiverGuard) {
            return typeof window.StartAddField === 'function';
          }

          var originalStartAddField = window.StartAddField;
          window.StartAddField = function (type) {
            if (type === slug && typeof window.GetFieldsByType === 'function') {
              var existing = window.GetFieldsByType([slug]) || [];
              if (existing.length) {
                window.alert(warning);
                return;
              }
            }

            return originalStartAddField.apply(this, arguments);
          };

          window.StartAddField._presswellTransceiverGuard = true;
          return true;
        }

        if (!guardSingleField()) {
          document.addEventListener('DOMContentLoaded', guardSingleField);
        }
      })(window, document);
    </script>
    <?php
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
   * Extract numeric field id from object or array field definitions.
   *
   * @param mixed $field Gravity Forms field.
   *
   * @return int
   */
  private function extract_field_id( $field ) {
    if ( is_object( $field ) && isset( $field->id ) ) {
      return absint( $field->id );
    }

    if ( is_array( $field ) && isset( $field['id'] ) ) {
      return absint( $field['id'] );
    }

    return 0;
  }
}
