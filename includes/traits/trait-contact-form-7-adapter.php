<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Contact Form 7 integration hooks.
 */
trait PWSL_Contact_Form_7_Trait {

  /**
   * Initialize Contact Form 7 hooks when available.
   */
  public function maybe_bootstrap_contact_form_7() {
    if ( ! defined( 'WPCF7_VERSION' ) && ! function_exists( 'wpcf7' ) ) {
      return;
    }

    add_action( 'wpcf7_enqueue_scripts', [ $this, 'maybe_enqueue_contact_form_7_assets' ] );
    add_filter( 'wpcf7_form_elements', [ $this, 'inject_contact_form_7_tracking_inputs' ], 20, 1 );
    add_filter( 'wpcf7_posted_data', [ $this, 'sanitize_contact_form_7_posted_data' ], 20, 1 );
  }

  /**
   * Enqueue front-end tracking script for Contact Form 7 forms.
   */
  public function maybe_enqueue_contact_form_7_assets() {
    $this->enqueue_tracking_script( PWSL::ADAPTER_CONTACT_FORM_7 );
  }

  /**
   * Inject hidden tracking inputs into Contact Form 7 form markup.
   *
   * @param string $html Contact Form 7 form elements HTML.
   *
   * @return string
   */
  public function inject_contact_form_7_tracking_inputs( $html ) {
    if ( false !== strpos( $html, 'data-presswell-transceiver-cf7="1"' ) ) {
      return $html;
    }

    $inputs = [];
    foreach ( $this->service->get_tracking_keys( PWSL::ADAPTER_CONTACT_FORM_7 ) as $key ) {
      $inputs[] = sprintf(
        '<input type="hidden" name="%1$s" value="" data-presswell-transceiver="%1$s" />',
        esc_attr( $key )
      );
    }

    if ( empty( $inputs ) ) {
      return $html;
    }

    $wrapper = sprintf(
      '<div class="presswell-cf7-transceiver" data-presswell-transceiver-cf7="1" style="display:none" aria-hidden="true">%s</div>',
      implode( '', $inputs )
    );

    return $html . $wrapper;
  }

  /**
   * Sanitize tracking values in Contact Form 7 posted data.
   *
   * @param array $posted_data Contact Form 7 posted data.
   *
   * @return array
   */
  public function sanitize_contact_form_7_posted_data( $posted_data ) {
    if ( ! is_array( $posted_data ) ) {
      $posted_data = [];
    }

    if ( empty( $_POST ) || ! is_array( $_POST ) ) {
      return $posted_data;
    }

    foreach ( $this->service->get_tracking_keys( PWSL::ADAPTER_CONTACT_FORM_7 ) as $key ) {
      $raw = null;

      if ( isset( $posted_data[ $key ] ) && ! is_array( $posted_data[ $key ] ) ) {
        $raw = $posted_data[ $key ];
      } elseif ( isset( $_POST[ $key ] ) && ! is_array( $_POST[ $key ] ) ) {
        $raw = wp_unslash( $_POST[ $key ] );
      }

      if ( null === $raw ) {
        continue;
      }

      $posted_data[ $key ] = $this->service->sanitize_tracking_value( $key, $raw );
    }

    return $posted_data;
  }
}
