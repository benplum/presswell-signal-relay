<?php
/**
 * Tests shared adapter asset and transceiver input behavior.
 */

require_once dirname( __DIR__ ) . '/includes/traits/trait-adapter-assets.php';

class PWTSR_Test_Adapter_Assets_Harness {
  use PWTSR_Adapter_Assets_Trait;

  /** @var bool */
  public $assets_registered = false;

  /** @var array */
  public $localized_objects = [];

  /** @var PWTSR_Tracking_Service */
  public $service;

  public function __construct( PWTSR_Tracking_Service $service ) {
    $this->service = $service;
  }

  public function enqueue_tracking_script_public( $context = 'core' ) {
    $this->enqueue_tracking_script( $context );
  }

  public function get_transceiver_classes_public( $adapter ) {
    return $this->get_transceiver_classes( $adapter );
  }

  public function render_transceiver_input_markup_public( $key, $name, $id, $value = '' ) {
    return $this->render_transceiver_input_markup( $key, $name, $id, $value );
  }

  public function wrap_transceiver_inputs_markup_public( $adapter, $inputs ) {
    return $this->wrap_transceiver_inputs_markup( $adapter, $inputs );
  }

  public function set_debug_field_mode( $enabled ) {
    $this->debug_field_mode = (bool) $enabled;
  }
}

class AdapterAssetsTraitTest extends WP_UnitTestCase {
  protected function tearDown(): void {
    wp_dequeue_script( PWTSR::ASSET_HANDLE_SCRIPT );
    wp_dequeue_script( PWTSR::ASSET_HANDLE_DEBUG_SCRIPT );
    wp_dequeue_style( PWTSR::ASSET_HANDLE_DEBUG_STYLE );

    wp_deregister_script( PWTSR::ASSET_HANDLE_SCRIPT );
    wp_deregister_script( PWTSR::ASSET_HANDLE_DEBUG_SCRIPT );
    wp_deregister_style( PWTSR::ASSET_HANDLE_DEBUG_STYLE );

    parent::tearDown();
  }

  public function test_enqueue_tracking_script_registers_and_localizes_config() {
    $harness = new PWTSR_Test_Adapter_Assets_Harness( new PWTSR_Tracking_Service() );
    $harness->set_debug_field_mode( false );

    $harness->enqueue_tracking_script_public( 'core' );

    $this->assertTrue( wp_script_is( PWTSR::ASSET_HANDLE_SCRIPT, 'registered' ) );
    $this->assertTrue( wp_script_is( PWTSR::ASSET_HANDLE_SCRIPT, 'enqueued' ) );
    $this->assertArrayHasKey( PWTSR::JS_OBJECT, $harness->localized_objects );

    $before = wp_scripts()->get_data( PWTSR::ASSET_HANDLE_SCRIPT, 'before' );
    $this->assertIsArray( $before );
    $this->assertNotEmpty( $before );

    $contains_config = false;
    foreach ( $before as $inline_script ) {
      if ( is_string( $inline_script ) && false !== strpos( $inline_script, 'window.' . PWTSR::JS_OBJECT ) ) {
        $contains_config = true;
        break;
      }
    }

    $this->assertTrue( $contains_config );
    $before_count = count( $before );

    $harness->enqueue_tracking_script_public( 'core' );

    $before_after = wp_scripts()->get_data( PWTSR::ASSET_HANDLE_SCRIPT, 'before' );
    $this->assertIsArray( $before_after );
    $this->assertCount( $before_count, $before_after );
  }

  public function test_enqueue_tracking_script_adds_debug_assets_when_debug_mode_is_enabled() {
    $harness = new PWTSR_Test_Adapter_Assets_Harness( new PWTSR_Tracking_Service() );
    $harness->set_debug_field_mode( true );

    $harness->enqueue_tracking_script_public( 'core' );

    $this->assertTrue( wp_style_is( PWTSR::ASSET_HANDLE_DEBUG_STYLE, 'enqueued' ) );
    $this->assertTrue( wp_script_is( PWTSR::ASSET_HANDLE_DEBUG_SCRIPT, 'enqueued' ) );
  }

  public function test_transceiver_input_helpers_switch_markup_for_debug_mode() {
    $harness = new PWTSR_Test_Adapter_Assets_Harness( new PWTSR_Tracking_Service() );

    $classes = $harness->get_transceiver_classes_public( 'contactform7<script>' );
    $this->assertSame( 'presswell-transceiver presswell-contactform7script-transceiver', $classes );

    $harness->set_debug_field_mode( false );
    $hidden_input = $harness->render_transceiver_input_markup_public( 'utm_source', 'utm_source', 'utm_source_id', 'newsletter' );
    $hidden_wrap  = $harness->wrap_transceiver_inputs_markup_public( 'contactform7', $hidden_input );

    $this->assertStringContainsString( 'type="hidden"', $hidden_input );
    $this->assertStringContainsString( 'style="display:none"', $hidden_wrap );

    $harness->set_debug_field_mode( true );
    $debug_input = $harness->render_transceiver_input_markup_public( 'utm_source', 'utm_source', 'utm_source_id', 'newsletter' );
    $debug_wrap  = $harness->wrap_transceiver_inputs_markup_public( 'contactform7', $debug_input );

    $this->assertStringContainsString( 'presswell-debug-field-row', $debug_input );
    $this->assertStringContainsString( 'readonly="readonly"', $debug_input );
    $this->assertStringContainsString( 'presswell-debug-toggle', $debug_wrap );
  }
}
