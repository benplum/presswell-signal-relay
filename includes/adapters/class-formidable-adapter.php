<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Formidable Forms adapter. Capture method: custom Tracking field.
 */
class PWTSR_Formidable_Adapter extends PWTSR_Form_Adapter {
  use PWTSR_Formidable_Trait;

  const KEY = PWTSR::ADAPTER_FORMIDABLE;

  /**
   * Register Formidable Forms hooks.
   */
  public function register() {
    $this->construct_formidable_trait();
  }
}
