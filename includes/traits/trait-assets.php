<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Site-wide registration of the tracking script and its config.
 */
trait PWTSR_Assets_Trait {

  /**
   * Whether the tracking script has been enqueued for this request.
   *
   * @var bool
   */
  private $tracking_assets_enqueued = false;

  /**
   * Attach front-end asset hooks.
   */
  protected function construct_assets_trait() {
    add_action( 'wp_enqueue_scripts', [ $this, 'maybe_enqueue_tracking_assets' ] );
  }

  /**
   * Load the tracking script on every front-end page while a form adapter is active.
   *
   * Attribution has to be captured on the page a visitor lands on, which is rarely the
   * page with the form, so this can't wait for a form to render.
   */
  public function maybe_enqueue_tracking_assets() {
    if ( empty( $this->get_adapters() ) ) {
      return;
    }

    if ( ! (bool) apply_filters( PWTSR::FILTER_ENQUEUE_TRACKING_SCRIPT, true ) ) {
      return;
    }

    $this->enqueue_tracking_assets();
  }

  /**
   * Enqueue the tracking script and print its config once per request.
   */
  public function enqueue_tracking_assets() {
    if ( $this->tracking_assets_enqueued ) {
      return;
    }

    wp_register_script(
      PWTSR::ASSET_HANDLE_SCRIPT,
      $this->get_asset_url( 'js/transceiver.js' ),
      [],
      PWTSR::VERSION,
      true
    );

    $payload = wp_json_encode( $this->get_service()->get_client_config( $this->get_client_contexts() ) );
    if ( is_string( $payload ) && '' !== $payload ) {
      wp_add_inline_script(
        PWTSR::ASSET_HANDLE_SCRIPT,
        'window[' . wp_json_encode( PWTSR::JS_OBJECT ) . ']=' . $payload . ';',
        'before'
      );
    }

    wp_enqueue_script( PWTSR::ASSET_HANDLE_SCRIPT );

    $this->tracking_assets_enqueued = true;
  }

  /**
   * Contexts whose tracking keys the browser should capture.
   *
   * One script serves every form on the page, so it captures the keys of every active adapter.
   *
   * @return string[]
   */
  protected function get_client_contexts() {
    return array_merge( [ 'core' ], array_keys( $this->get_adapters() ) );
  }
}
