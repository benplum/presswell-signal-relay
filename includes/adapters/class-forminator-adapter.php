<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Forminator adapter. Capture method: hidden inputs appended to every form.
 */
class PWTSR_Forminator_Adapter extends PWTSR_Form_Adapter {
  use PWTSR_Forminator_Trait;

  const KEY = PWTSR::ADAPTER_FORMINATOR;

  /**
   * Register Forminator hooks.
   */
  public function register() {
    $this->construct_forminator_trait();
  }
}
