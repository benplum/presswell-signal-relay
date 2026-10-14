<?php
/**
 * Tests shared adapter asset and transceiver input behavior.
 */

require_once dirname( __DIR__ ) . '/includes/traits/trait-adapter-assets.php';

class PWTSR_Test_Adapter_Assets_Harness {
  use PWTSR_Adapter_Assets_Trait;


  /** @var PWTSR_Tracking_Service */
  public $service;

  public function __construct( PWTSR_Tracking_Service $service ) {
    $this->service = $service;
  }

  public function enqueue_tracking_script_public() {
    $this->enqueue_tracking_script();
  }

  public function render_transceiver_input_markup_public( $key, $name, $id ) {
    return $this->render_transceiver_input_markup( $key, $name, $id );
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

  public function test_enqueue_tracking_script_adds_debug_assets_when_debug_mode_is_enabled() {
    $harness = new PWTSR_Test_Adapter_Assets_Harness( new PWTSR_Tracking_Service() );
    $harness->set_debug_field_mode( true );

    $harness->enqueue_tracking_script_public();

    $this->assertTrue( wp_script_is( PWTSR::ASSET_HANDLE_SCRIPT, 'enqueued' ) );

    $this->assertTrue( wp_style_is( PWTSR::ASSET_HANDLE_DEBUG_STYLE, 'enqueued' ) );
    $this->assertTrue( wp_script_is( PWTSR::ASSET_HANDLE_DEBUG_SCRIPT, 'enqueued' ) );
  }

  public function test_transceiver_input_helpers_switch_markup_for_debug_mode() {
    $harness = new PWTSR_Test_Adapter_Assets_Harness( new PWTSR_Tracking_Service() );

    $this->assertStringContainsString( 'class="pwtsr-transceiver pwtsr-contactform7script-transceiver"', $harness->wrap_transceiver_inputs_markup_public( 'contactform7<script>', '<input />' ) );

    $harness->set_debug_field_mode( false );
    $hidden_input = $harness->render_transceiver_input_markup_public( 'utm_source', 'utm_source', 'utm_source_id' );
    $hidden_wrap  = $harness->wrap_transceiver_inputs_markup_public( 'contactform7', $hidden_input );

    $this->assertStringContainsString( 'type="hidden"', $hidden_input );
    $this->assertStringContainsString( 'style="display:none"', $hidden_wrap );

    $harness->set_debug_field_mode( true );
    $debug_input = $harness->render_transceiver_input_markup_public( 'utm_source', 'utm_source', 'utm_source_id' );
    $debug_wrap  = $harness->wrap_transceiver_inputs_markup_public( 'contactform7', $debug_input );

    $this->assertStringContainsString( 'pwtsr-debug-field-row', $debug_input );
    $this->assertStringContainsString( 'readonly="readonly"', $debug_input );
    $this->assertStringContainsString( 'pwtsr-debug-toggle', $debug_wrap );
  }
}
