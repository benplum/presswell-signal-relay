<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

/**
 * Admin Settings -> Tracking Signal Relay markup.
 */
?>
<div class="wrap pwtsr-settings">
  <h1><?php echo esc_html__( 'Tracking Signal Relay', 'presswell-signal-relay' ); ?></h1>
  <form method="post" action="options.php">
    <?php
      settings_fields( PWTSR::SETTINGS_GROUP );
      do_settings_sections( PWTSR::SETTINGS_PAGE_SLUG );
      submit_button();
    ?>
  </form>
</div>
