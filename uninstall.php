<?php
/**
 * Removes Presswell Tracking Signal Relay data when the plugin is deleted (never on deactivation).
 *
 * Settings are only removed when "Remove Data" is on, so deleting and reinstalling to
 * troubleshoot keeps them. Tracking values saved with form entries belong to the form
 * plugins and are never touched.
 *
 * @package Presswell Tracking Signal Relay
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
  exit;
}

require_once __DIR__ . '/includes/helpers/class-constants.php';

if ( ! function_exists( 'pwtsr_uninstall_site' ) ) {
  /**
   * Remove the plugin's data for the current site.
   */
  function pwtsr_uninstall_site() {
    $settings = get_option( PWTSR::SETTINGS_KEY );
    if ( is_array( $settings ) && isset( $settings['remove_data_on_uninstall'] ) && 'on' === $settings['remove_data_on_uninstall'] ) {
      delete_option( PWTSR::SETTINGS_KEY );
    }
  }
}

if ( is_multisite() ) {
  foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $pwtsr_site_id ) {
    switch_to_blog( $pwtsr_site_id );
    pwtsr_uninstall_site();
    restore_current_blog();
  }
} else {
  pwtsr_uninstall_site();
}
