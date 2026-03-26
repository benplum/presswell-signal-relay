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
    $this->assertSame( [], $defaults['custom_params'] );
  }

  public function test_sanitize_settings_normalizes_debug_mode_and_custom_params() {
    $sanitized = $this->plugin->sanitize_settings(
      [
        'debug_mode' => 'on',
        'custom_params' => "utm_id,affiliate_id\nCampaign_Source\ninvalid value",
      ]
    );

    $this->assertSame( 'on', $sanitized['debug_mode'] );
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
}
