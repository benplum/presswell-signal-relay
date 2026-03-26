<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Forminator adapter for Presswell Tracking Signal Relay.
 * Capture method: HTML injection (hidden inputs appended at render time).
 */
class PWTSR_Forminator_Adapter implements PWTSR_Form_Adapter_Interface {
  use PWTSR_Adapter_Assets_Trait;
  use PWTSR_Forminator_Trait;

  /**
   * Shared tracking service instance.
   *
   * @var PWTSR_Tracking_Service
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
   * @param PWTSR_Tracking_Service $service Shared tracking service.
   */
  public function __construct( PWTSR_Tracking_Service $service ) {
    $this->service = $service;
  }

  /**
   * Return adapter key.
   *
   * @return string
   */
  public function key() {
    return PWTSR::ADAPTER_FORMINATOR;
  }

  /**
   * Register adapter boot hooks.
   */
  public function register() {
    $this->maybe_bootstrap_forminator();
  }
}
