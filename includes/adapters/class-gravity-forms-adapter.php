<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Gravity Forms adapter. Capture method: custom Tracking field.
 */
class PWTSR_Gravity_Forms_Adapter extends PWTSR_Form_Adapter {
  use PWTSR_Gravity_Forms_Trait;

  const KEY = PWTSR::ADAPTER_GRAVITY_FORMS;

  /**
   * Register Gravity Forms hooks.
   */
  public function register() {
    $this->construct_gravity_forms_trait();
  }
}
