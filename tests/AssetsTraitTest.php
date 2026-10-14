<?php
/**
 * Tests site-wide loading of the tracking script.
 */

class AssetsTraitTest extends WP_UnitTestCase {
  /** @var Presswell_Tracking_Signal_Relay */
  protected $plugin;

  protected function setUp(): void {
    parent::setUp();

    $this->plugin = presswell_tracking_signal_relay();
    $this->reset_plugin_state();

    // Other suites may have left the handle registered with its inline config.
    wp_deregister_script( PWTSR::ASSET_HANDLE_SCRIPT );
  }

  protected function tearDown(): void {
    $this->reset_plugin_state();

    wp_dequeue_script( PWTSR::ASSET_HANDLE_SCRIPT );
    wp_deregister_script( PWTSR::ASSET_HANDLE_SCRIPT );

    parent::tearDown();
  }

  public function test_hook_is_registered_on_front_end_enqueue() {
    $this->assertNotFalse( has_action( 'wp_enqueue_scripts', [ $this->plugin, 'maybe_enqueue_tracking_assets' ] ) );
  }

  public function test_script_loads_on_every_page_while_an_adapter_is_active() {
    $this->set_adapters( [ PWTSR::ADAPTER_CONTACT_FORM_7 => new PWTSR_Test_Assets_Adapter() ] );

    $this->plugin->maybe_enqueue_tracking_assets();

    $this->assertTrue( wp_script_is( PWTSR::ASSET_HANDLE_SCRIPT, 'enqueued' ) );
  }

  public function test_script_is_skipped_without_active_adapters() {
    $this->plugin->maybe_enqueue_tracking_assets();

    $this->assertFalse( wp_script_is( PWTSR::ASSET_HANDLE_SCRIPT, 'enqueued' ) );
  }

  public function test_filter_can_turn_off_site_wide_loading() {
    $this->set_adapters( [ PWTSR::ADAPTER_CONTACT_FORM_7 => new PWTSR_Test_Assets_Adapter() ] );
    add_filter( 'pwtsr_enqueue_tracking_script', '__return_false' );

    $this->plugin->maybe_enqueue_tracking_assets();

    $this->assertFalse( wp_script_is( PWTSR::ASSET_HANDLE_SCRIPT, 'enqueued' ) );
  }

  public function test_config_is_printed_once_with_keys_from_every_adapter() {
    $this->set_adapters(
      [
        PWTSR::ADAPTER_CONTACT_FORM_7 => new PWTSR_Test_Assets_Adapter(),
        PWTSR::ADAPTER_GRAVITY_FORMS  => new PWTSR_Test_Assets_Adapter(),
      ]
    );

    add_filter(
      'pwtsr_tracking_keys',
      static function ( $keys, $context ) {
        if ( PWTSR::ADAPTER_GRAVITY_FORMS === $context ) {
          $keys[] = 'gf_only';
        }

        return $keys;
      },
      10,
      2
    );

    $this->plugin->enqueue_tracking_assets();
    $this->plugin->enqueue_tracking_assets();

    // WordPress seeds the inline list with `false`, so count only the scripts.
    $before = array_values( array_filter( (array) wp_scripts()->get_data( PWTSR::ASSET_HANDLE_SCRIPT, 'before' ), 'is_string' ) );
    $this->assertCount( 1, $before );
    $this->assertStringContainsString( 'window["' . PWTSR::JS_OBJECT . '"]=', $before[0] );
    $this->assertStringContainsString( '"gf_only"', $before[0] );
  }

  /**
   * Replace the registered adapters.
   *
   * @param array $adapters Adapters keyed by slug.
   */
  private function set_adapters( $adapters ) {
    $registry = new PWTSR_Adapter_Registry();
    foreach ( $adapters as $key => $adapter ) {
      $adapter->slug = $key;
      $registry->add( $adapter );
    }

    $this->set_plugin_property( 'adapter_registry', $registry );
  }

  /**
   * Clear request-level state on the singleton.
   */
  private function reset_plugin_state() {
    $this->set_plugin_property( 'tracking_assets_enqueued', false );
    $this->set_plugin_property( 'adapter_registry', new PWTSR_Adapter_Registry() );
  }

  /**
   * Set a private property on the plugin singleton.
   *
   * @param string $name  Property name.
   * @param mixed  $value Property value.
   */
  private function set_plugin_property( $name, $value ) {
    $property = new ReflectionProperty( $this->plugin, $name );
    $property->setAccessible( true );
    $property->setValue( $this->plugin, $value );
  }
}

/**
 * Adapter stand-in that registers nothing.
 */
class PWTSR_Test_Assets_Adapter implements PWTSR_Form_Adapter_Interface {
  /** @var string */
  public $slug = 'test';

  /**
   * Return adapter key.
   *
   * @return string
   */
  public function key() {
    return $this->slug;
  }

  /**
   * Register nothing.
   */
  public function register() {}
}
