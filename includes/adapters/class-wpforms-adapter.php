<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * WPForms adapter. Capture method: custom Tracking field.
 */
class PWTSR_WPForms_Adapter extends PWTSR_Form_Adapter {
  use PWTSR_WPForms_Trait;

  const KEY = PWTSR::ADAPTER_WPFORMS;

  /**
   * Register WPForms hooks.
   */
  public function register() {
    $this->construct_wpforms_trait();
  }
}
