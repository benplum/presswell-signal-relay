<?php
/**
 * PHPUnit bootstrap file.
 */

$composer_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $composer_autoload ) ) {
  require_once $composer_autoload;
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
  $polyfills_path = dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills';
  if ( file_exists( $polyfills_path . '/phpunitpolyfills-autoload.php' ) ) {
    define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $polyfills_path );
  }
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
  $tmp_dir    = sys_get_temp_dir();
  $_tests_dir = rtrim( $tmp_dir, '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir ) ) {
  $_tests_dir = '/tmp/wordpress-tests-lib';
}

$functions_file = $_tests_dir . '/includes/functions.php';
$bootstrap_file = $_tests_dir . '/includes/bootstrap.php';

if ( ! file_exists( $functions_file ) || ! file_exists( $bootstrap_file ) ) {
  $phpunit6_functions = $_tests_dir . '/includes/phpunit6/functions.php';
  $phpunit6_bootstrap = $_tests_dir . '/includes/phpunit6/bootstrap.php';

  if ( file_exists( $phpunit6_functions ) && file_exists( $phpunit6_bootstrap ) ) {
    $functions_file = $phpunit6_functions;
    $bootstrap_file = $phpunit6_bootstrap;
  }
}

if ( ! file_exists( $functions_file ) || ! file_exists( $bootstrap_file ) ) {
  fwrite( STDERR, 'Could not find WordPress test suite in ' . $_tests_dir . ".\n" );
  exit( 1 );
}

require $functions_file;

function _pwtsr_manually_load_plugin() {
  require dirname( __DIR__ ) . '/presswell-signal-relay.php';
}
tests_add_filter( 'muplugins_loaded', '_pwtsr_manually_load_plugin' );

require $bootstrap_file;
