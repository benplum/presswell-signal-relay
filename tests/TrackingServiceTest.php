<?php
/**
 * Tests core tracking service behavior.
 */

class TrackingServiceTest extends WP_UnitTestCase {
  /** @var PWTSR_Tracking_Service */
  protected $service;

  protected function setUp(): void {
    parent::setUp();

    $this->service = new PWTSR_Tracking_Service();
    delete_option( PWTSR::SETTINGS_KEY );
  }

  protected function tearDown(): void {
    delete_option( PWTSR::SETTINGS_KEY );
    remove_all_filters( 'pwtsr_tracking_keys' );
    remove_all_filters( 'pwtsr_tracking_ttl' );
    remove_all_filters( 'pwtsr_storage_key' );

    parent::tearDown();
  }

  public function test_get_tracking_keys_includes_default_and_custom_keys() {
    update_option(
      PWTSR::SETTINGS_KEY,
      [
        'custom_params' => [ 'campaign_source', 'utm_source' ],
      ]
    );

    $keys = $this->service->get_tracking_keys();

    $this->assertContains( 'utm_source', $keys );
    $this->assertContains( 'campaign_source', $keys );
    $this->assertSame( count( $keys ), count( array_unique( $keys ) ) );
  }

  public function test_get_ttl_seconds_falls_back_to_default_when_filter_returns_zero() {
    add_filter(
      'pwtsr_tracking_ttl',
      static function () {
        return 0;
      }
    );

    $this->assertSame( PWTSR::TTL_SECONDS, $this->service->get_ttl_seconds() );
  }

  public function test_get_client_config_uses_fallback_storage_key_when_filter_returns_empty() {
    add_filter(
      'pwtsr_storage_key',
      static function () {
        return '';
      }
    );

    add_filter(
      'pwtsr_tracking_ttl',
      static function () {
        return 123;
      }
    );

    $config = $this->service->get_client_config();

    $this->assertSame( PWTSR::STORAGE_KEY, $config['storageKey'] );
    $this->assertSame( 123, $config['ttl'] );
    $this->assertIsArray( $config['transceiverKeys'] );
    $this->assertNotEmpty( $config['transceiverKeys'] );
  }

  public function test_sanitize_tracking_value_handles_scalars_urls_and_length_limits() {
    $this->assertSame( '', $this->service->sanitize_tracking_value( 'utm_source', [ 'bad' ] ) );

    $clean_url = $this->service->sanitize_tracking_value( 'landing_page', 'https://example.com/path?x=1' );
    $this->assertStringStartsWith( 'https://example.com/path', $clean_url );

    $invalid_url = $this->service->sanitize_tracking_value( 'referrer', 'javascript:alert(1)' );
    $this->assertSame( '', $invalid_url );

    $long = str_repeat( 'a', PWTSR::MAX_VALUE_LENGTH + 20 );
    $truncated = $this->service->sanitize_tracking_value( 'utm_campaign', $long );
    $this->assertSame( PWTSR::MAX_VALUE_LENGTH, strlen( $truncated ) );
  }
}
