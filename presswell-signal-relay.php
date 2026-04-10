<?php
/**
 * Plugin Name: Presswell Tracking Signal Relay
 * Description: Capture UTM and click attribution parameters across a visitor session for supported form plugins.
 * Author: Presswell
 * Version: 1.0.0
 * Plugin URI: https://wordpress.org/plugins/presswell-signal-relay
 * Author URI: https://presswell.co
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: presswell-signal-relay
 *
 * @package Presswell Tracking Signal Relay
 * @author Presswell
 */

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

require_once __DIR__ . '/includes/helpers/class-constants.php';
require_once __DIR__ . '/includes/helpers/class-transceiver-markup.php';
require_once __DIR__ . '/includes/services/class-tracking-service.php';
require_once __DIR__ . '/includes/adapters/interface-form-adapter.php';
require_once __DIR__ . '/includes/adapters/class-adapter-registry.php';
require_once __DIR__ . '/includes/traits/trait-adapter-bootstrap.php';
require_once __DIR__ . '/includes/traits/trait-service-access.php';
require_once __DIR__ . '/includes/traits/trait-helpers.php';
require_once __DIR__ . '/includes/traits/trait-settings.php';
require_once __DIR__ . '/includes/traits/trait-pages.php';

if ( ! class_exists( 'Presswell_Tracking_Signal_Relay' ) ) {
  /**
  * Bootstrap container for all Presswell Tracking Signal Relay features.
   */
  final class Presswell_Tracking_Signal_Relay {
    use PWTSR_Adapter_Bootstrap_Trait;
    use PWTSR_Service_Access_Trait;
    use Presswell_Tracking_Signal_Relay_Helpers_Trait;
    use PWTSR_Settings_Trait;
    use PWTSR_Pages_Trait;

    const PLUGIN_FILE = __FILE__;

    /**
     * Cached singleton instance.
     *
     * @var Presswell_Tracking_Signal_Relay|null
     */
    private static $instance = null;

    /** Build core dependencies and defer integration bootstrap. */
    protected function __construct() {
      $this->construct_settings_trait();
      $this->construct_pages_trait();
      $this->construct_adapter_bootstrap_trait();
    }

    /**
     * Prevent cloning the singleton.
     */
    public function __clone() {}

    /**
     * Prevent unserializing the singleton.
     */
    public function __wakeup() {}

    /**
     * Return shared plugin instance.
     *
     * @return Presswell_Tracking_Signal_Relay
     */
    public static function instance() {
      if ( null === self::$instance ) {
        self::$instance = new self();
      }

      return self::$instance;
    }

  }
}

if ( ! function_exists( 'presswell_tracking_signal_relay' ) ) {
  /**
   * Access helper for plugin singleton.
   *
   * @return Presswell_Tracking_Signal_Relay
   */
  // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound -- Public helper name is intentional and kept for backward compatibility.
  function presswell_tracking_signal_relay() {
    return Presswell_Tracking_Signal_Relay::instance();
  }
}

presswell_tracking_signal_relay();
