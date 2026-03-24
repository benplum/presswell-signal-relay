<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Shared script registration/localization behavior for adapters.
 */
trait PWSL_Adapter_Assets_Trait {

  /**
   * Ensure runtime script is registered once.
   */
  private function register_tracking_assets() {
    if ( $this->assets_registered ) {
      return;
    }

    wp_register_script(
      PWSL::ASSET_HANDLE_SCRIPT,
      plugins_url( 'assets/js/transceiver.js', PWSL_PLUGIN_FILE ),
      [],
      PWSL::VERSION,
      true
    );

    $this->assets_registered = true;
  }

  /**
   * Enqueue and localize tracking script.
   *
   * @param string $context Integration context.
   */
  protected function enqueue_tracking_script( $context = 'core' ) {
    $this->register_tracking_assets();

    wp_enqueue_script( PWSL::ASSET_HANDLE_SCRIPT );

    if ( isset( $this->localized_objects[ PWSL::JS_OBJECT ] ) ) {
      return;
    }

    wp_localize_script(
      PWSL::ASSET_HANDLE_SCRIPT,
      PWSL::JS_OBJECT,
      $this->service->get_client_config( $context )
    );

    $this->localized_objects[ PWSL::JS_OBJECT ] = true;
  }
}
