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
    remove_all_filters( 'pwtsr_attribution_model' );

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

  public function test_sanitize_tracking_value_keeps_percent_encoding() {
    $this->assertSame( 'https://example.com/?q=red%20shoes&p=%2Fa', $this->service->sanitize_tracking_value( 'landing_page', 'https://example.com/?q=red%20shoes&p=%2Fa' ) );
    $this->assertSame( '?q=red%20shoes', $this->service->sanitize_tracking_value( 'landing_query', '?q=red%20shoes' ) );
  }

  public function test_sanitize_tracking_value_strips_tags_and_line_breaks() {
    $this->assertSame( 'spring launch', $this->service->sanitize_tracking_value( 'utm_campaign', "<b>spring</b>\r\nlaunch" ) );
    $this->assertSame( 'ab', $this->service->sanitize_tracking_value( 'utm_campaign', "a\x00b" ) );
  }

  public function test_get_client_config_merges_keys_across_contexts() {
    add_filter(
      'pwtsr_tracking_keys',
      static function ( $keys, $context ) {
        if ( 'gravityforms' === $context ) {
          $keys[] = 'gf_only';
        }

        return $keys;
      },
      10,
      2
    );

    $this->assertNotContains( 'gf_only', $this->service->get_client_config( 'core' )['transceiverKeys'] );

    $keys = $this->service->get_client_config( [ 'core', 'contactform7', 'gravityforms' ] )['transceiverKeys'];
    $this->assertContains( 'gf_only', $keys );
    $this->assertSame( array_values( array_unique( $keys ) ), $keys );
  }

  public function test_tracking_keys_include_a_first_touch_copy_of_each_key() {
    $keys = $this->service->get_tracking_keys();

    $this->assertContains( 'utm_source', $keys );
    $this->assertContains( 'first_utm_source', $keys );
    $this->assertContains( 'first_referrer', $keys );
    $this->assertCount( 2 * count( $this->service->get_capture_keys() ), $keys );
  }

  public function test_single_models_store_plain_keys_only() {
    foreach ( [ PWTSR::MODEL_LAST_TOUCH, PWTSR::MODEL_FIRST_TOUCH ] as $model ) {
      update_option( PWTSR::SETTINGS_KEY, [ 'attribution_model' => $model ] );

      $this->assertSame( $model, $this->service->get_attribution_model() );
      $this->assertSame( $this->service->get_capture_keys(), $this->service->get_tracking_keys() );
      $this->assertSame( $model, $this->service->get_client_config()['attributionModel'] );
    }
  }

  public function test_attribution_model_filter_overrides_setting_and_rejects_unknown_values() {
    update_option( PWTSR::SETTINGS_KEY, [ 'attribution_model' => PWTSR::MODEL_LAST_TOUCH ] );

    add_filter(
      'pwtsr_attribution_model',
      static function () {
        return PWTSR::MODEL_FIRST_TOUCH;
      }
    );
    $this->assertSame( PWTSR::MODEL_FIRST_TOUCH, $this->service->get_attribution_model() );

    remove_all_filters( 'pwtsr_attribution_model' );
    add_filter(
      'pwtsr_attribution_model',
      static function () {
        return 'linear';
      }
    );
    $this->assertSame( PWTSR::MODEL_BOTH, $this->service->get_attribution_model() );
  }

  public function test_client_config_sends_capture_keys_and_model() {
    $config = $this->service->get_client_config();

    $this->assertNotContains( 'first_utm_source', $config['transceiverKeys'] );
    $this->assertSame( PWTSR::MODEL_BOTH, $config['attributionModel'] );
    $this->assertSame( PWTSR::FIRST_TOUCH_PREFIX, $config['firstTouchPrefix'] );
    $this->assertSame( PWTSR::DERIVED_TRACKING_KEYS, $config['derivedKeys'] );
  }

  public function test_ttl_comes_from_expiry_setting() {
    $this->assertSame( 7 * DAY_IN_SECONDS, $this->service->get_ttl_seconds() );

    update_option( PWTSR::SETTINGS_KEY, [ 'expiry_value' => 30, 'expiry_unit' => 'days' ] );
    $this->assertSame( 30 * DAY_IN_SECONDS, $this->service->get_ttl_seconds() );
    $this->assertSame( 30 * DAY_IN_SECONDS, $this->service->get_client_config()['ttl'] );

    update_option( PWTSR::SETTINGS_KEY, [ 'expiry_value' => 5000, 'expiry_unit' => 'days' ] );
    $this->assertSame( PWTSR::TTL_MAX_SECONDS, $this->service->get_ttl_seconds() );

    update_option( PWTSR::SETTINGS_KEY, [ 'expiry_value' => 3, 'expiry_unit' => 'weeks' ] );
    $this->assertSame( PWTSR::TTL_SECONDS, $this->service->get_ttl_seconds() );
  }

  public function test_ttl_filter_receives_setting_value() {
    update_option( PWTSR::SETTINGS_KEY, [ 'expiry_value' => 2, 'expiry_unit' => 'hours' ] );

    $seen = null;
    add_filter(
      'pwtsr_tracking_ttl',
      static function ( $ttl ) use ( &$seen ) {
        $seen = $ttl;
        return $ttl;
      }
    );

    $this->service->get_ttl_seconds();
    $this->assertSame( 2 * HOUR_IN_SECONDS, $seen );
  }

  public function test_first_touch_keys_share_url_rules() {
    $this->assertSame( '', $this->service->sanitize_tracking_value( 'first_referrer', 'javascript:alert(1)' ) );
    $this->assertSame( 'https://example.com/?q=a%20b', $this->service->sanitize_tracking_value( 'first_landing_page', 'https://example.com/?q=a%20b' ) );
  }
}
