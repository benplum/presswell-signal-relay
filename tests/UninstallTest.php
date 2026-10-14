<?php
/**
 * Tests covering uninstall and the Remove Data choice.
 */

class UninstallTest extends WP_UnitTestCase {
  protected function tearDown(): void {
    delete_option( PWTSR::SETTINGS_KEY );

    parent::tearDown();
  }

  /**
   * Run the uninstall script.
   */
  private function uninstall() {
    if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
      define( 'WP_UNINSTALL_PLUGIN', 'presswell-signal-relay/presswell-signal-relay.php' );
    }

    include dirname( __DIR__ ) . '/uninstall.php';
    wp_cache_flush();
  }

  public function test_settings_are_kept_by_default() {
    update_option( PWTSR::SETTINGS_KEY, [ 'custom_params' => [ 'affiliate_id' ] ] );

    $this->uninstall();

    $this->assertSame( [ 'affiliate_id' ], get_option( PWTSR::SETTINGS_KEY )['custom_params'] );
  }

  public function test_settings_are_removed_when_opted_in() {
    update_option( PWTSR::SETTINGS_KEY, [ 'remove_data_on_uninstall' => 'on' ] );

    $this->uninstall();

    $this->assertFalse( get_option( PWTSR::SETTINGS_KEY ) );
  }
}
