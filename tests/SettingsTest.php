<?php
/**
 * Tests settings defaults, sanitization, and visibility checks.
 */

class SettingsTest extends WP_UnitTestCase {
  /** @var Presswell_Tracking_Signal_Relay */
  protected $plugin;

  protected function setUp(): void {
    parent::setUp();
    $this->plugin = presswell_tracking_signal_relay();
    delete_option( PWTSR::SETTINGS_KEY );
  }

  protected function tearDown(): void {
    delete_option( PWTSR::SETTINGS_KEY );
    wp_set_current_user( 0 );

    parent::tearDown();
  }

  public function test_default_settings_structure() {
    $defaults = $this->plugin->get_default_settings();

    $this->assertArrayHasKey( 'debug_mode', $defaults );
    $this->assertArrayHasKey( 'custom_params', $defaults );
    $this->assertSame( 'off', $defaults['debug_mode'] );
    $this->assertSame( 'off', $defaults['remove_data_on_uninstall'] );
    $this->assertSame( [], $defaults['custom_params'] );
    $this->assertSame( PWTSR::MODEL_BOTH, $defaults['attribution_model'] );
    $this->assertSame( 7, $defaults['expiry_value'] );
    $this->assertSame( 'days', $defaults['expiry_unit'] );
  }

  public function test_sanitize_settings_accepts_valid_model_and_expiry() {
    $sanitized = $this->plugin->sanitize_settings(
      [
        'attribution_model' => 'first',
        'expiry_value'      => '30',
        'expiry_unit'       => 'days',
      ]
    );

    $this->assertSame( PWTSR::MODEL_FIRST_TOUCH, $sanitized['attribution_model'] );
    $this->assertSame( 30, $sanitized['expiry_value'] );
    $this->assertSame( 'days', $sanitized['expiry_unit'] );
  }

  public function test_sanitize_settings_rejects_invalid_model_and_expiry() {
    $sanitized = $this->plugin->sanitize_settings(
      [
        'attribution_model' => 'linear',
        'expiry_value'      => [ '5' ],
        'expiry_unit'       => 'weeks',
      ]
    );

    $this->assertSame( PWTSR::MODEL_BOTH, $sanitized['attribution_model'] );
    $this->assertSame( 7, $sanitized['expiry_value'] );
    $this->assertSame( 'days', $sanitized['expiry_unit'] );
  }

  public function test_sanitize_settings_caps_expiry_at_one_year() {
    $days  = $this->plugin->sanitize_settings( [ 'expiry_value' => 9999, 'expiry_unit' => 'days' ] );
    $hours = $this->plugin->sanitize_settings( [ 'expiry_value' => 99999, 'expiry_unit' => 'hours' ] );

    $this->assertSame( 365, $days['expiry_value'] );
    $this->assertSame( 8760, $hours['expiry_value'] );
  }

  public function test_settings_page_renders_model_and_expiry_fields() {
    $admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
    wp_set_current_user( $admin_id );
    update_option( PWTSR::SETTINGS_KEY, [ 'attribution_model' => 'last', 'expiry_value' => 7, 'expiry_unit' => 'days' ] );

    ob_start();
    $this->plugin->render_attribution_model_field();
    $this->plugin->render_expiry_field();
    $html = ob_get_clean();

    $this->assertStringContainsString( 'pwtsr-button-group', $html );
    $this->assertMatchesRegularExpression( '/value="last"\s+checked/', $html );
    $this->assertStringContainsString( 'value="7"', $html );
    $this->assertMatchesRegularExpression( '/value="days"\s+selected/', $html );
  }

  public function test_sanitize_settings_normalizes_debug_mode_and_custom_params() {
    $sanitized = $this->plugin->sanitize_settings(
      [
        'debug_mode' => 'on',
        'custom_params' => "utm_id,affiliate_id\nCampaign_Source\ninvalid value",
      ]
    );

    $this->assertSame( 'on', $sanitized['debug_mode'] );
    $this->assertSame( 'off', $sanitized['remove_data_on_uninstall'] );
    $this->assertSame( 'on', $this->plugin->sanitize_settings( [ 'remove_data_on_uninstall' => 'on' ] )['remove_data_on_uninstall'] );
    $this->assertSame( [ 'utm_id', 'affiliate_id', 'campaign_source', 'invalidvalue' ], $sanitized['custom_params'] );
  }

  public function test_get_settings_merges_saved_values_with_defaults() {
    update_option(
      PWTSR::SETTINGS_KEY,
      [
        'custom_params' => [ 'affiliate_id' ],
      ]
    );

    $settings = $this->plugin->get_settings();

    $this->assertSame( 'off', $settings['debug_mode'] );
    $this->assertSame( [ 'affiliate_id' ], $settings['custom_params'] );
  }

  public function test_should_show_debug_styles_respects_role_and_setting() {
    update_option(
      PWTSR::SETTINGS_KEY,
      [
        'debug_mode' => 'on',
        'custom_params' => [],
      ]
    );

    $admin_id = self::factory()->user->create( [ 'role' => 'administrator' ] );
    wp_set_current_user( $admin_id );

    $this->assertTrue( $this->plugin->should_show_debug_styles() );

    update_option(
      PWTSR::SETTINGS_KEY,
      [
        'debug_mode' => 'off',
        'custom_params' => [],
      ]
    );

    $this->assertFalse( $this->plugin->should_show_debug_styles() );
  }

  public function test_only_the_selected_model_description_is_shown() {
    update_option( PWTSR::SETTINGS_KEY, [ 'attribution_model' => PWTSR::MODEL_FIRST_TOUCH ] );

    ob_start();
    $this->plugin->render_attribution_model_field();
    $html = ob_get_clean();

    $this->assertMatchesRegularExpression( '/data-pwtsr-model="first">/', $html );
    $this->assertMatchesRegularExpression( '/data-pwtsr-model="last" hidden>/', $html );
    $this->assertMatchesRegularExpression( '/data-pwtsr-model="both" hidden>/', $html );
  }
}
