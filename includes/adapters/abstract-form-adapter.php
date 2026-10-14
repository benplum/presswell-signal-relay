<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! class_exists( 'PWTSR_Form_Adapter' ) ) {
  /**
   * Base for form plugin adapters: holds the tracking service and the adapter key.
   *
   * Each adapter adds its form plugin's trait and calls that trait's construct method
   * from register().
   */
  abstract class PWTSR_Form_Adapter implements PWTSR_Form_Adapter_Interface {
    use PWTSR_Adapter_Assets_Trait;

    /**
     * Adapter key, one of the PWTSR::ADAPTER_* constants.
     */
    const KEY = '';

    /**
     * Shared tracking service instance.
     *
     * @var PWTSR_Tracking_Service
     */
    protected $service;

    /**
     * Store the shared tracking service.
     *
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
      return static::KEY;
    }
  }
}
