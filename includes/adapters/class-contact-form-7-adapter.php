<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Contact Form 7 adapter. Capture method: hidden inputs appended to every form.
 */
class PWTSR_Contact_Form_7_Adapter extends PWTSR_Form_Adapter {
  use PWTSR_Contact_Form_7_Trait;

  const KEY = PWTSR::ADAPTER_CONTACT_FORM_7;

  /**
   * Register Contact Form 7 hooks.
   */
  public function register() {
    $this->construct_contact_form_7_trait();
  }
}
