<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Shared script registration/localization behavior for adapters.
 */
trait PWTSR_Adapter_Assets_Trait {

  /**
   * Cache debug mode state for the current request.
   *
   * @var bool|null
   */
  private $debug_field_mode = null;

  /**
   * Make sure the tracking script is on a page that renders a form.
   *
   * The main plugin usually loads it site-wide already; this covers sites that turn that
   * off with the pwtsr_enqueue_tracking_script filter.
   */
  protected function enqueue_tracking_script() {
    presswell_tracking_signal_relay()->enqueue_tracking_assets();
    $this->maybe_enqueue_debug_assets();
  }

  /**
   * Enqueue debug styles that reveal tracking containers for privileged users.
   */
  private function maybe_enqueue_debug_assets() {
    if ( ! $this->is_debug_field_mode() ) {
      return;
    }

    static $debug_styles_enqueued = false;
    if ( $debug_styles_enqueued ) {
      return;
    }

    wp_enqueue_style(
      PWTSR::ASSET_HANDLE_DEBUG_STYLE,
      plugin_dir_url( Presswell_Tracking_Signal_Relay::PLUGIN_FILE ) . 'assets/css/debug.css',
      [],
      PWTSR::VERSION
    );

    wp_enqueue_script(
      PWTSR::ASSET_HANDLE_DEBUG_SCRIPT,
      plugin_dir_url( Presswell_Tracking_Signal_Relay::PLUGIN_FILE ) . 'assets/js/debug.js',
      [],
      PWTSR::VERSION,
      true
    );

    $debug_styles_enqueued = true;
  }

  /**
   * Whether debug field mode is active, cached for the request.
   *
   * @return bool
   */
  protected function is_debug_field_mode() {
    if ( null === $this->debug_field_mode ) {
      $this->debug_field_mode = PWTSR_Transceiver_Markup::is_debug_mode();
    }

    return $this->debug_field_mode;
  }

  /**
   * Render one tracking input.
   *
   * @param string $key  Tracking key.
   * @param string $name Input name attribute.
   * @param string $id   Input id attribute.
   *
   * @return string
   */
  protected function render_transceiver_input_markup( $key, $name, $id ) {
    return PWTSR_Transceiver_Markup::render_input( $key, $name, $id, $this->is_debug_field_mode() );
  }

  /**
   * Wrap tracking inputs in the shared container.
   *
   * @param string $adapter Adapter slug.
   * @param string $inputs  Inputs HTML.
   *
   * @return string
   */
  protected function wrap_transceiver_inputs_markup( $adapter, $inputs ) {
    return PWTSR_Transceiver_Markup::render( $adapter, $inputs, $this->is_debug_field_mode() );
  }
}
