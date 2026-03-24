<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Contact Form 7 adapter for Presswell Signal Relay.
 */
class PWSL_Contact_Form_7_Adapter implements PWSL_Form_Adapter_Interface {
  use PWSL_Adapter_Assets_Trait;
  use PWSL_Contact_Form_7_Trait;

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
    return PWSL::ADAPTER_CONTACT_FORM_7;
  }

  /**
   * Register adapter boot hooks.
   */
  public function register() {
    add_action( 'plugins_loaded', [ $this, 'maybe_bootstrap_contact_form_7' ], 20 );
  }
}
