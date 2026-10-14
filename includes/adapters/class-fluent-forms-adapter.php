<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Fluent Forms adapter. Capture method: custom Tracking field.
 */
class PWTSR_Fluent_Forms_Adapter extends PWTSR_Form_Adapter {
  use PWTSR_Fluent_Forms_Trait;

  const KEY = PWTSR::ADAPTER_FLUENT_FORMS;

  /**
   * Register Fluent Forms hooks.
   */
  public function register() {
    $this->construct_fluent_forms_trait();
  }
}
