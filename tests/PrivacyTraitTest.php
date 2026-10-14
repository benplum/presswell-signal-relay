<?php
/**
 * Tests privacy policy text, consent settings, and WP Consent API registration.
 */

class PrivacyTraitTest extends WP_UnitTestCase {
  /** @var Presswell_Tracking_Signal_Relay */
  protected $plugin;

  protected function setUp(): void {
    parent::setUp();

    $this->plugin = presswell_tracking_signal_relay();
    delete_option( PWTSR::SETTINGS_KEY );
  }

  protected function tearDown(): void {
    delete_option( PWTSR::SETTINGS_KEY );
    remove_all_filters( 'pwtsr_require_consent' );
    remove_all_filters( 'pwtsr_consent_category' );

    parent::tearDown();
  }

  public function test_policy_text_describes_storage_and_expiry() {
    $text = $this->plugin->get_privacy_policy_text();

    $this->assertStringContainsString( 'local storage', $text );
    $this->assertStringContainsString( '7 days', $text );
    $this->assertStringNotContainsString( 'consent', $text );
  }

  public function test_policy_text_follows_expiry_and_consent_settings() {
    update_option( PWTSR::SETTINGS_KEY, [ 'expiry_value' => 30, 'expiry_unit' => 'days', 'require_consent' => 'on' ] );

    $text = $this->plugin->get_privacy_policy_text();

    $this->assertStringContainsString( '30 days', $text );
    $this->assertStringContainsString( 'consent to marketing', $text );
  }

  public function test_policy_content_is_registered_on_admin_init() {
    $this->assertNotFalse( has_action( 'admin_init', [ $this->plugin, 'add_privacy_policy_content' ] ) );
  }

  public function test_plugin_registers_with_wp_consent_api() {
    $filter = 'wp_consent_api_registered_' . plugin_basename( Presswell_Tracking_Signal_Relay::PLUGIN_FILE );

    $this->assertTrue( apply_filters( $filter, false ) );
  }

  public function test_client_config_carries_consent_settings() {
    $service = $this->plugin->get_service();

    $this->assertFalse( $service->get_client_config()['requireConsent'] );
    $this->assertSame( 'marketing', $service->get_client_config()['consentCategory'] );

    update_option( PWTSR::SETTINGS_KEY, [ 'require_consent' => 'on' ] );
    add_filter(
      'pwtsr_consent_category',
      static function () {
        return 'statistics';
      }
    );

    $config = $service->get_client_config();
    $this->assertTrue( $config['requireConsent'] );
    $this->assertSame( 'statistics', $config['consentCategory'] );
  }

  public function test_require_consent_setting_is_sanitized() {
    $this->assertSame( 'off', $this->plugin->get_default_settings()['require_consent'] );
    $this->assertSame( 'on', $this->plugin->sanitize_settings( [ 'require_consent' => 'on' ] )['require_consent'] );
    $this->assertSame( 'off', $this->plugin->sanitize_settings( [] )['require_consent'] );
  }

  public function test_settings_warn_when_consent_is_required_without_wp_consent_api() {
    $render = function () {
      ob_start();
      $this->plugin->render_require_consent_field();
      return ob_get_clean();
    };

    $this->assertFalse( function_exists( 'wp_has_consent' ) );
    $this->assertStringNotContainsString( 'pwtsr-consent-warning', $render() );

    update_option( PWTSR::SETTINGS_KEY, [ 'require_consent' => 'on' ] );
    $this->assertStringContainsString( 'pwtsr-consent-warning', $render() );
  }
}
