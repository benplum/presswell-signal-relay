<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Registry for all available Signal Relay form adapters.
 */
class PWSL_Adapter_Registry {

  /**
   * Registered adapters keyed by adapter id.
   *
   * @var PWSL_Form_Adapter_Interface[]
   */
  private $adapters = [];

  /**
   * Add an adapter to the registry.
   *
   * @param PWSL_Form_Adapter_Interface $adapter Adapter instance.
   */
  public function add( PWSL_Form_Adapter_Interface $adapter ) {
    $key = sanitize_key( $adapter->key() );
    if ( '' === $key ) {
      return;
    }

    $this->adapters[ $key ] = $adapter;
  }

  /**
   * Register hooks for all adapters.
   */
  public function register_all() {
    foreach ( $this->adapters as $adapter ) {
      $adapter->register();
    }
  }

  /**
   * Return all registered adapters.
   *
   * @return PWSL_Form_Adapter_Interface[]
   */
  public function all() {
    return $this->adapters;
  }
}
