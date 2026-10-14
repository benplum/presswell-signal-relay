<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Settings registration and rendering helpers.
 */
trait PWTSR_Settings_Trait {
  /**
   * Attach settings hooks.
   */
  protected function construct_settings_trait() {
    add_action( 'admin_init', [ $this, 'register_settings' ] );
  }

  /**
   * Register plugin settings section and fields.
   */
  public function register_settings() {
    register_setting(
      PWTSR::SETTINGS_GROUP,
      PWTSR::SETTINGS_KEY,
      [
        'type'              => 'array',
        'sanitize_callback' => [ $this, 'sanitize_settings' ],
        'default'           => $this->get_default_settings(),
      ]
    );

    // No section heading: the page title covers it, as on other Presswell settings screens.
    add_settings_section(
      PWTSR::SETTINGS_SECTION,
      '',
      '__return_false',
      PWTSR::SETTINGS_PAGE_SLUG
    );

    add_settings_field(
      'attribution_model',
      __( 'Attribution Model', 'presswell-signal-relay' ),
      [ $this, 'render_attribution_model_field' ],
      PWTSR::SETTINGS_PAGE_SLUG,
      PWTSR::SETTINGS_SECTION
    );

    add_settings_field(
      'expiry',
      __( 'Attribution Expiry', 'presswell-signal-relay' ),
      [ $this, 'render_expiry_field' ],
      PWTSR::SETTINGS_PAGE_SLUG,
      PWTSR::SETTINGS_SECTION,
      [ 'label_for' => 'pwtsr-expiry-value' ]
    );

    add_settings_field(
      'require_consent',
      __( 'Visitor Consent', 'presswell-signal-relay' ),
      [ $this, 'render_require_consent_field' ],
      PWTSR::SETTINGS_PAGE_SLUG,
      PWTSR::SETTINGS_SECTION
    );

    add_settings_field(
      'custom_params',
      __( 'Custom Query Params', 'presswell-signal-relay' ),
      [ $this, 'render_custom_params_field' ],
      PWTSR::SETTINGS_PAGE_SLUG,
      PWTSR::SETTINGS_SECTION
    );

    add_settings_field(
      'debug_mode',
      __( 'Debug Display', 'presswell-signal-relay' ),
      [ $this, 'render_debug_mode_field' ],
      PWTSR::SETTINGS_PAGE_SLUG,
      PWTSR::SETTINGS_SECTION
    );

    add_settings_field(
      'remove_data_on_uninstall',
      __( 'Remove Data', 'presswell-signal-relay' ),
      [ $this, 'render_remove_data_field' ],
      PWTSR::SETTINGS_PAGE_SLUG,
      PWTSR::SETTINGS_SECTION
    );
  }

  /**
   * Sanitize the settings payload.
   *
   * @param array $input Raw settings payload.
   *
   * @return array
   */
  public function sanitize_settings( $input ) {
    $input = is_array( $input ) ? $input : [];

    $defaults = $this->get_default_settings();

    $model = isset( $input['attribution_model'] ) ? sanitize_key( (string) $input['attribution_model'] ) : '';
    $unit  = isset( $input['expiry_unit'] ) ? sanitize_key( (string) $input['expiry_unit'] ) : '';
    $unit  = isset( PWTSR::EXPIRY_UNITS[ $unit ] ) ? $unit : $defaults['expiry_unit'];
    $value = isset( $input['expiry_value'] ) && is_scalar( $input['expiry_value'] ) ? absint( $input['expiry_value'] ) : 0;
    $max   = (int) floor( PWTSR::TTL_MAX_SECONDS / PWTSR::EXPIRY_UNITS[ $unit ] );

    return [
      'debug_mode'               => ! empty( $input['debug_mode'] ) ? 'on' : 'off',
      'remove_data_on_uninstall' => ! empty( $input['remove_data_on_uninstall'] ) ? 'on' : 'off',
      'require_consent'          => ! empty( $input['require_consent'] ) ? 'on' : 'off',
      'custom_params'            => $this->sanitize_custom_params_list( isset( $input['custom_params'] ) ? $input['custom_params'] : '' ),
      'attribution_model'        => in_array( $model, PWTSR::ATTRIBUTION_MODELS, true ) ? $model : $defaults['attribution_model'],
      'expiry_value'             => $value ? min( $value, $max ) : $defaults['expiry_value'],
      'expiry_unit'              => $unit,
    ];
  }

  /**
   * Get default settings.
   *
   * @return array
   */
  public function get_default_settings() {
    return [
      'debug_mode'               => 'off',
      'remove_data_on_uninstall' => 'off',
      'require_consent'          => 'off',
      'custom_params'            => [],
      'attribution_model'        => PWTSR::MODEL_BOTH,
      'expiry_value'             => PWTSR::EXPIRY_DEFAULT_VALUE,
      'expiry_unit'              => PWTSR::EXPIRY_DEFAULT_UNIT,
    ];
  }

  /**
   * Retrieve settings merged with defaults.
   *
   * @return array
   */
  public function get_settings() {
    $settings = wp_parse_args( get_option( PWTSR::SETTINGS_KEY, [] ), $this->get_default_settings() );
    $settings['debug_mode'] = ( isset( $settings['debug_mode'] ) && 'on' === $settings['debug_mode'] ) ? 'on' : 'off';

    return $settings;
  }

  /**
   * Whether debug mode is enabled by setting.
   *
   * @return bool
   */
  public function is_debug_mode_enabled() {
    $settings = $this->get_settings();

    return isset( $settings['debug_mode'] ) && 'on' === $settings['debug_mode'];
  }

  /**
   * Whether debug styles should be shown to the current visitor.
   *
   * @return bool
   */
  public function should_show_debug_styles() {
    return $this->is_debug_mode_enabled() && is_user_logged_in() && current_user_can( 'edit_others_posts' );
  }

  /**
   * Render the debug mode settings field.
   */
  public function render_debug_mode_field() {
    $settings = $this->get_settings();
    ?>
    <label>
      <input type="checkbox" name="<?php echo esc_attr( PWTSR::SETTINGS_KEY ); ?>[debug_mode]" value="on" <?php checked( isset( $settings['debug_mode'] ) ? $settings['debug_mode'] : 'off', 'on' ); ?> />
      <?php echo esc_html__( 'Show tracking field containers on the frontend for editors and admins.', 'presswell-signal-relay' ); ?>
    </label>
    <?php
  }

  /**
   * Render the attribution model choice.
   */
  public function render_attribution_model_field() {
    $settings = $this->get_settings();
    $name     = PWTSR::SETTINGS_KEY . '[attribution_model]';
    $choices  = [
      PWTSR::MODEL_BOTH        => __( 'First and last', 'presswell-signal-relay' ),
      PWTSR::MODEL_LAST_TOUCH  => __( 'Last touch', 'presswell-signal-relay' ),
      PWTSR::MODEL_FIRST_TOUCH => __( 'First touch', 'presswell-signal-relay' ),
    ];
    ?>
    <fieldset class="pwtsr-button-group" role="radiogroup" aria-label="<?php echo esc_attr__( 'Attribution Model', 'presswell-signal-relay' ); ?>">
      <?php foreach ( $choices as $model => $label ) : ?>
        <label class="pwtsr-button-group-option">
          <input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $model ); ?>" <?php checked( $settings['attribution_model'], $model ); ?> />
          <span class="button"><?php echo esc_html( $label ); ?></span>
        </label>
      <?php endforeach; ?>
    </fieldset>
    <?php
    $descriptions = [
      PWTSR::MODEL_LAST_TOUCH  => __( 'Attribution based on the most recent page view.', 'presswell-signal-relay' ),
      PWTSR::MODEL_FIRST_TOUCH => __( 'Attribution based on the initial page view.', 'presswell-signal-relay' ),
      PWTSR::MODEL_BOTH        => __( 'Attribution based on both initial and most recent page view.', 'presswell-signal-relay' ),
    ];
    ?>
    <?php foreach ( $descriptions as $model => $description ) : ?>
      <p class="description pwtsr-model-description" data-pwtsr-model="<?php echo esc_attr( $model ); ?>"<?php if ( $settings['attribution_model'] !== $model ) : ?> hidden<?php endif; ?>>
        <?php echo esc_html( $description ); ?>
      </p>
    <?php endforeach; ?>
    <p class="description">
      <?php echo esc_html__( 'Changing attribution model does not affect existing data.', 'presswell-signal-relay' ); ?>
    </p>
    <?php
  }

  /**
   * Render the attribution expiry inputs.
   */
  public function render_expiry_field() {
    $settings = $this->get_settings();
    $units    = [
      'hours' => __( 'Hours', 'presswell-signal-relay' ),
      'days'  => __( 'Days', 'presswell-signal-relay' ),
    ];
    ?>
    <input
      type="number"
      id="pwtsr-expiry-value"
      name="<?php echo esc_attr( PWTSR::SETTINGS_KEY ); ?>[expiry_value]"
      value="<?php echo esc_attr( (string) $settings['expiry_value'] ); ?>"
      min="1"
      step="1"
      class="small-text"
    />
    <label class="screen-reader-text" for="pwtsr-expiry-unit"><?php echo esc_html__( 'Expiry unit', 'presswell-signal-relay' ); ?></label>
    <select id="pwtsr-expiry-unit" name="<?php echo esc_attr( PWTSR::SETTINGS_KEY ); ?>[expiry_unit]">
      <?php foreach ( $units as $unit => $label ) : ?>
        <option value="<?php echo esc_attr( $unit ); ?>" <?php selected( $settings['expiry_unit'], $unit ); ?>><?php echo esc_html( $label ); ?></option>
      <?php endforeach; ?>
    </select>
    <?php
  }

  /**
   * Render the visitor consent checkbox.
   */
  public function render_require_consent_field() {
    $settings = $this->get_settings();
    ?>
    <label>
      <input type="checkbox" name="<?php echo esc_attr( PWTSR::SETTINGS_KEY ); ?>[require_consent]" value="on" <?php checked( isset( $settings['require_consent'] ) ? $settings['require_consent'] : 'off', 'on' ); ?> />
      <?php echo esc_html__( 'Wait for marketing consent through WP Consent API', 'presswell-signal-relay' ); ?>
    </label>
    <?php if ( 'on' === $settings['require_consent'] && ! $this->is_wp_consent_api_active() ) : ?>
      <div class="notice notice-warning inline pwtsr-consent-warning">
        <p>
          <?php
          printf(
            /* translators: %s: JavaScript call, presswellSignalRelay.setConsent( true ) */
            esc_html__( 'No WP Consent API plugin found. Ensure your consent banner calls %s.', 'presswell-signal-relay' ),
            '<code>presswellSignalRelay.setConsent( true )</code>'
          );
          ?>
        </p>
      </div>
    <?php endif; ?>
    <?php
  }

  /**
   * Whether the WP Consent API is available to report visitor consent.
   *
   * @return bool
   */
  protected function is_wp_consent_api_active() {
    return function_exists( 'wp_has_consent' );
  }

  /**
   * Render the remove-data-on-uninstall checkbox.
   */
  public function render_remove_data_field() {
    $settings = $this->get_settings();
    ?>
    <label>
      <input type="checkbox" name="<?php echo esc_attr( PWTSR::SETTINGS_KEY ); ?>[remove_data_on_uninstall]" value="on" <?php checked( isset( $settings['remove_data_on_uninstall'] ) ? $settings['remove_data_on_uninstall'] : 'off', 'on' ); ?> />
      <?php echo esc_html__( 'Delete settings when the plugin is deleted. Does not delete stored tracking values.', 'presswell-signal-relay' ); ?>
    </label>
    <?php
  }

  /**
   * Render custom tracking parameter textarea.
   */
  public function render_custom_params_field() {
    $settings = $this->get_settings();
    $rows = isset( $settings['custom_params'] ) && is_array( $settings['custom_params'] ) ? $settings['custom_params'] : [];
    $value = implode( "\n", $rows );
    ?>
    <textarea
      name="<?php echo esc_attr( PWTSR::SETTINGS_KEY ); ?>[custom_params]"
      rows="6"
      class="large-text code"
      placeholder="utm_id&#10;affiliate_id&#10;campaign_source"
    ><?php echo esc_textarea( $value ); ?></textarea>
    <p class="description">
      <?php echo esc_html__( 'Add custom query parameter keys to track. Use commas or new lines (for example: utm_id, affiliate_id). Keys are matched in any letter case.', 'presswell-signal-relay' ); ?>
    </p>
    <?php
  }

  /**
   * Normalize comma/newline separated custom parameter input.
   *
   * @param mixed $raw Raw setting value.
   *
   * @return string[]
   */
  private function sanitize_custom_params_list( $raw ) {
    if ( is_array( $raw ) ) {
      $raw = implode( "\n", $raw );
    }

    if ( ! is_scalar( $raw ) ) {
      return [];
    }

    $parts = preg_split( '/[\r\n,]+/', (string) $raw );
    if ( ! is_array( $parts ) ) {
      return [];
    }

    $keys = [];
    foreach ( $parts as $part ) {
      $clean = sanitize_key( trim( (string) $part ) );
      if ( '' === $clean ) {
        continue;
      }

      $keys[] = $clean;
    }

    return array_values( array_unique( $keys ) );
  }

  /**
   * Render plugin settings page.
   */
  public function render_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
      return;
    }

    $this->render_view( 'settings-page.php' );
  }
}
