<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Forminator adapter for Presswell Signal Relay.
 */
class PWSL_Forminator_Adapter implements PWSL_Form_Adapter_Interface {
  use PWSL_Adapter_Assets_Trait;
  use PWSL_Forminator_Trait;

  /**
   * Shared tracking service instance.
   *
   * @var PWSL_Tracking_Service
   */
  private $service;

  /**
   * Whether assets are registered for this request.
   *
   * @var bool
   */
  private $assets_registered = false;

  /**
   * Tracks localized JS objects to avoid duplicate calls.
   *
   * @var array
   */
  private $localized_objects = [];

  /**
   * @param PWSL_Tracking_Service $service Shared tracking service.
   */
  public function __construct( PWSL_Tracking_Service $service ) {
    $this->service = $service;
  }

  /**
   * Return adapter key.
   *
   * @return string
   */
  public function key() {
    return PWSL::ADAPTER_FORMINATOR;
  }

  /**
   * Register adapter boot hooks.
   */
  public function register() {
    add_action( 'plugins_loaded', [ $this, 'maybe_bootstrap_forminator' ], 20 );
  }
}
