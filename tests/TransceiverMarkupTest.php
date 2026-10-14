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
    unset( $_COOKIE['pwtsr_debug_closed'] );
    $this->assertFalse( PWTSR_Transceiver_Markup::is_debug_panel_closed_from_cookie() );

    $_COOKIE['pwtsr_debug_closed'] = '0';
    $this->assertFalse( PWTSR_Transceiver_Markup::is_debug_panel_closed_from_cookie() );

    $_COOKIE['pwtsr_debug_closed'] = '1';
    $this->assertTrue( PWTSR_Transceiver_Markup::is_debug_panel_closed_from_cookie() );
  }

  public function test_render_wrapper_returns_debug_markup_with_open_panel_by_default() {
    unset( $_COOKIE['pwtsr_debug_closed'] );

    $markup = PWTSR_Transceiver_Markup::render_wrapper(
      'pwtsr-transceiver pwtsr-contactform7-transceiver',
      'contactform7',
      '<input type="text" name="utm_source" />',
      true
    );

    $this->assertStringContainsString( 'data-pwtsr-transceiver="1"', $markup );
    $this->assertStringContainsString( 'aria-expanded="true"', $markup );
    $this->assertStringContainsString( '<div class="pwtsr-debug-fields">', $markup );
    $this->assertStringContainsString( 'Tracking Signals', $markup );
  }

  public function test_render_wrapper_returns_debug_markup_with_closed_panel_when_cookie_is_set() {
    $_COOKIE['pwtsr_debug_closed'] = '1';

    $markup = PWTSR_Transceiver_Markup::render_wrapper(
      'pwtsr-transceiver pwtsr-wpforms-transceiver',
      'wpforms',
      '<input type="text" name="utm_source" />',
      true
    );

    $this->assertStringContainsString( 'aria-expanded="false"', $markup );
    $this->assertStringContainsString( 'class="pwtsr-debug-fields" hidden', $markup );
  }

  public function test_render_wrapper_returns_hidden_container_in_non_debug_mode() {
    $markup = PWTSR_Transceiver_Markup::render_wrapper(
      'pwtsr-transceiver pwtsr-forminator-transceiver',
      'forminator',
      '<input type="hidden" name="utm_source" />',
      false
    );

    $this->assertStringContainsString( 'style="display:none"', $markup );
    $this->assertStringContainsString( 'aria-hidden="true"', $markup );
    $this->assertStringNotContainsString( 'pwtsr-debug-toggle', $markup );
  }

  public function test_render_wrapper_returns_empty_string_when_inputs_are_blank() {
    $this->assertSame( '', PWTSR_Transceiver_Markup::render_wrapper( 'x', 'cf7', '', true ) );
    $this->assertSame( '', PWTSR_Transceiver_Markup::render_wrapper( 'x', 'cf7', '   ', false ) );
  }

  public function test_debug_toggle_label_is_translatable() {
    $filter = static function ( $translation, $text, $domain ) {
      return 'Tracking Signals' === $text && 'presswell-signal-relay' === $domain ? 'Signaux' : $translation;
    };
    add_filter( 'gettext', $filter, 10, 3 );

    $html = PWTSR_Transceiver_Markup::render_wrapper( 'x', 'contactform7', '<input />', true );
    remove_filter( 'gettext', $filter, 10 );

    $this->assertStringContainsString( '>Signaux<', $html );
  }

  public function test_content_context_and_escaping() {
    $this->assertSame( 'url', PWTSR_Transceiver_Markup::get_content_context( 'https://example.com/?a=[tracking_all]' ) );
    $this->assertSame( 'url', PWTSR_Transceiver_Markup::get_content_context( '/thanks?src={tracking_utm_source}' ) );
    $this->assertSame( 'html', PWTSR_Transceiver_Markup::get_content_context( '<p>Thanks</p>' ) );
    $this->assertSame( 'text', PWTSR_Transceiver_Markup::get_content_context( 'Thanks for your message' ) );

    $this->assertSame( 'a%20%26%20b', PWTSR_Transceiver_Markup::escape_for_context( 'a & b', 'url' ) );
    $this->assertSame( 'a &amp; b', PWTSR_Transceiver_Markup::escape_for_context( 'a & b', 'html' ) );
    $this->assertSame( 'a & b', PWTSR_Transceiver_Markup::escape_for_context( 'a & b', 'text' ) );
  }

  public function test_render_builds_prefixed_wrapper_and_inputs() {
    $html = PWTSR_Transceiver_Markup::render( 'wpforms', PWTSR_Transceiver_Markup::render_input( 'utm_source', 'f[utm_source]', 'f-utm', false ), false );

    $this->assertStringContainsString( 'class="pwtsr-transceiver pwtsr-wpforms-transceiver"', $html );
    $this->assertStringContainsString( 'data-pwtsr-transceiver="utm_source"', $html );
    $this->assertStringContainsString( 'type="hidden"', $html );
  }

  public function test_debug_panel_includes_hidden_consent_notice() {
    $debug  = PWTSR_Transceiver_Markup::render( 'contactform7', '<input />', true );
    $hidden = PWTSR_Transceiver_Markup::render( 'contactform7', '<input />', false );

    $this->assertStringContainsString( '<p class="pwtsr-debug-consent" hidden>Waiting for visitor consent.', $debug );
    $this->assertStringNotContainsString( 'pwtsr-debug-consent', $hidden );
  }
}
