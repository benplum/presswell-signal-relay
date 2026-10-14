<?php

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Privacy policy text and WP Consent API registration.
 */
trait PWTSR_Privacy_Trait {
  /**
   * Attach privacy hooks.
   */
  protected function construct_privacy_trait() {
    add_action( 'admin_init', [ $this, 'add_privacy_policy_content' ] );

    // Tells the WP Consent API that this plugin checks consent before storing anything.
    add_filter( 'wp_consent_api_registered_' . plugin_basename( Presswell_Tracking_Signal_Relay::PLUGIN_FILE ), '__return_true' );
  }

  /**
   * Suggest text for the site's privacy policy (Settings -> Privacy).
   */
  public function add_privacy_policy_content() {
    if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
      return;
    }

    wp_add_privacy_policy_content(
      __( 'Presswell Tracking Signal Relay', 'presswell-signal-relay' ),
      wp_kses_post( wpautop( $this->get_privacy_policy_text(), false ) )
    );
  }

  /**
   * Suggested privacy policy text.
   *
   * @return string
   */
  public function get_privacy_policy_text() {
    $paragraphs = [
      __( 'When you visit this site, your browser keeps a record of how you arrived: campaign and advertising parameters in the address you followed (such as utm_source, or ad click IDs like gclid and fbclid), the first page you viewed, and the website that referred you. This record is kept in your browser\'s local storage, not in a cookie, and is not sent anywhere while you browse.', 'presswell-signal-relay' ),
      __( 'If you submit a form on this site, the record is saved with your submission so we can understand which campaigns and websites bring in enquiries. It is kept with the submission for as long as we keep the submission itself.', 'presswell-signal-relay' ),
      sprintf(
        /* translators: %s: length of time, such as "7 days" */
        __( 'The record is cleared from your browser after %s without a visit to this site. You can also clear it at any time by clearing your browser\'s site data.', 'presswell-signal-relay' ),
        $this->format_expiry( $this->get_service()->get_ttl_seconds() )
      ),
    ];

    if ( $this->get_service()->is_consent_required() ) {
      $paragraphs[] = __( 'This record is only kept after you consent to marketing in our consent banner, and it is removed if you withdraw that consent.', 'presswell-signal-relay' );
    }

    return implode( "\n\n", $paragraphs );
  }

  /**
   * Describe the expiry exactly, in days or hours.
   *
   * human_time_diff() rounds (7 days reads "1 week", 30 days "1 month"), which a privacy
   * policy shouldn't.
   *
   * @param int $seconds Expiry in seconds.
   *
   * @return string
   */
  private function format_expiry( $seconds ) {
    $seconds = max( 1, (int) $seconds );

    if ( 0 === $seconds % DAY_IN_SECONDS ) {
      $days = (int) ( $seconds / DAY_IN_SECONDS );

      /* translators: %d: number of days */
      return sprintf( _n( '%d day', '%d days', $days, 'presswell-signal-relay' ), $days );
    }

    $hours = (int) ceil( $seconds / HOUR_IN_SECONDS );

    /* translators: %d: number of hours */
    return sprintf( _n( '%d hour', '%d hours', $hours, 'presswell-signal-relay' ), $hours );
  }
}
