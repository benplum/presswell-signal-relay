<?php
/**
 * Tests transceiver wrapper markup behavior.
 */

class TransceiverMarkupTest extends WP_UnitTestCase {
  /** @var array */
  protected $cookie_backup;

  protected function setUp(): void {
    parent::setUp();
    $this->cookie_backup = $_COOKIE;
  }

  protected function tearDown(): void {
    $_COOKIE = $this->cookie_backup;
    parent::tearDown();
  }

  public function test_is_debug_panel_closed_from_cookie_reads_expected_values() {
    unset( $_COOKIE['pwsrDebugClosed'] );
    $this->assertFalse( PWTSR_Transceiver_Markup::is_debug_panel_closed_from_cookie() );

    $_COOKIE['pwsrDebugClosed'] = '0';
    $this->assertFalse( PWTSR_Transceiver_Markup::is_debug_panel_closed_from_cookie() );

    $_COOKIE['pwsrDebugClosed'] = '1';
    $this->assertTrue( PWTSR_Transceiver_Markup::is_debug_panel_closed_from_cookie() );
  }

  public function test_render_wrapper_returns_debug_markup_with_open_panel_by_default() {
    unset( $_COOKIE['pwsrDebugClosed'] );

    $markup = PWTSR_Transceiver_Markup::render_wrapper(
      'presswell-transceiver presswell-contactform7-transceiver',
      'contactform7',
      '<input type="text" name="utm_source" />',
      true
    );

    $this->assertStringContainsString( 'data-presswell-transceiver="1"', $markup );
    $this->assertStringContainsString( 'aria-expanded="true"', $markup );
    $this->assertStringNotContainsString( ' hidden', $markup );
    $this->assertStringContainsString( 'Tracking Signals', $markup );
  }

  public function test_render_wrapper_returns_debug_markup_with_closed_panel_when_cookie_is_set() {
    $_COOKIE['pwsrDebugClosed'] = '1';

    $markup = PWTSR_Transceiver_Markup::render_wrapper(
      'presswell-transceiver presswell-wpforms-transceiver',
      'wpforms',
      '<input type="text" name="utm_source" />',
      true
    );

    $this->assertStringContainsString( 'aria-expanded="false"', $markup );
    $this->assertStringContainsString( 'class="presswell-debug-fields" hidden', $markup );
  }

  public function test_render_wrapper_returns_hidden_container_in_non_debug_mode() {
    $markup = PWTSR_Transceiver_Markup::render_wrapper(
      'presswell-transceiver presswell-forminator-transceiver',
      'forminator',
      '<input type="hidden" name="utm_source" />',
      false
    );

    $this->assertStringContainsString( 'style="display:none"', $markup );
    $this->assertStringContainsString( 'aria-hidden="true"', $markup );
    $this->assertStringNotContainsString( 'presswell-debug-toggle', $markup );
  }

  public function test_render_wrapper_returns_empty_string_when_inputs_are_blank() {
    $this->assertSame( '', PWTSR_Transceiver_Markup::render_wrapper( 'x', 'cf7', '', true ) );
    $this->assertSame( '', PWTSR_Transceiver_Markup::render_wrapper( 'x', 'cf7', '   ', false ) );
  }
}
