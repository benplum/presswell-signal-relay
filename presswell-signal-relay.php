<?php
/**
 * Plugin Name: Presswell Signal Relay
 * Description: Capture UTM and click attribution parameters across a visitor session for supported form plugins.
 * Author: Presswell
 * Version: 1.2.0
 * Plugin URI: http://wordpress.org/plugins/
 * Author URI: http://presswell.co
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: presswell-signal-relay
 *
 * @package Presswell Signal Relay
 * @author Presswell
 *
 * Presswell Signal Relay is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * Presswell Signal Relay is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Presswell Signal Relay. If not, see <http://www.gnu.org/licenses/>.
 */

// ?utm_source=google&utm_medium=cpc&utm_campaign=winter_launch&utm_content=cta_banner&utm_term=snow_boots&gclid=Cj0KCQiAzbi-ABCD1234&fbclid=fb.9876543210XYZ&msclkid=MSCLKID123456&ttclid=TTCLID-987654&landing_page=https%3A%2F%2Fexample.com%2Fwinter-sale&landing_query=%3Fref%3Dpartner&referrer=https%3A%2F%2Fpartner-site.com

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

if ( ! defined( 'PWSL_PLUGIN_FILE' ) ) {
  define( 'PWSL_PLUGIN_FILE', __FILE__ );
}

require_once __DIR__ . '/includes/helpers/class-constants.php';
require_once __DIR__ . '/includes/services/class-tracking-service.php';
require_once __DIR__ . '/includes/traits/trait-adapter-assets.php';
require_once __DIR__ . '/includes/traits/trait-gravity-forms-adapter.php';
require_once __DIR__ . '/includes/traits/trait-forminator-adapter.php';
require_once __DIR__ . '/includes/traits/trait-contact-form-7-adapter.php';
require_once __DIR__ . '/includes/adapters/interface-form-adapter.php';
require_once __DIR__ . '/includes/adapters/class-adapter-registry.php';
require_once __DIR__ . '/includes/adapters/class-gravity-forms-adapter.php';
require_once __DIR__ . '/includes/adapters/class-gravity-forms-field.php';
require_once __DIR__ . '/includes/adapters/class-forminator-adapter.php';
require_once __DIR__ . '/includes/adapters/class-contact-form-7-adapter.php';

if ( ! class_exists( 'Presswell_Signal_Relay' ) ) {

  /**
   * Composition root for all plugin features.
   */
  final class Presswell_Signal_Relay {

    /**
     * Cached singleton instance.
     *
    * @var Presswell_Signal_Relay|null
     */
    private static $instance = null;

    /**
     * Shared tracking service instance.
     *
     * @var PWSL_Tracking_Service
     */
    private $service;

    /**
     * Adapter registry.
     *
     * @var PWSL_Adapter_Registry
     */
    private $adapter_registry;

    /**
     * Build dependencies and register adapter hooks.
     */
    protected function __construct() {
      PWSL::instance();

      $this->service = new PWSL_Tracking_Service();
      $this->adapter_registry = new PWSL_Adapter_Registry();
      $this->adapter_registry->add( new PWSL_Gravity_Forms_Adapter( $this->service ) );
      $this->adapter_registry->add( new PWSL_Forminator_Adapter( $this->service ) );
      $this->adapter_registry->add( new PWSL_Contact_Form_7_Adapter( $this->service ) );
      $this->adapter_registry->register_all();
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
    * @return Presswell_Signal_Relay
     */
    public static function instance() {
      if ( null === self::$instance ) {
        self::$instance = new self();
      }

      return self::$instance;
    }

    /**
     * Expose service for supporting classes.
     *
     * @return PWSL_Tracking_Service
     */
    public function get_service() {
      return $this->service;
    }

    /**
     * Expose registered adapters.
     *
     * @return PWSL_Form_Adapter_Interface[]
     */
    public function get_adapters() {
      return $this->adapter_registry->all();
    }
  }
}

if ( ! function_exists( 'presswell_signal_relay' ) ) {
  /**
   * Access helper for plugin singleton.
   *
   * @return Presswell_Signal_Relay
   */
  function presswell_signal_relay() {
    return Presswell_Signal_Relay::instance();
  }
}

presswell_signal_relay();
