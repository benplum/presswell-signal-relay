<?php
/**
 * Tests adapter bootstrap orchestration behavior.
 */

class PWTSR_Test_Counting_Registry extends PWTSR_Adapter_Registry {
  /** @var int */
  public $register_all_calls = 0;

  public function register_all() {
    $this->register_all_calls++;
    parent::register_all();
  }
}

class AdapterBootstrapTest extends WP_UnitTestCase {
  /** @var Presswell_Tracking_Signal_Relay */
  protected $plugin;

  protected function setUp(): void {
    parent::setUp();
    $this->plugin = presswell_tracking_signal_relay();
  }

  public function test_bootstrap_integrations_is_idempotent() {
    $registry = new PWTSR_Test_Counting_Registry();

    $this->set_private_property( $this->plugin, 'adapter_registry', $registry );
    $this->set_private_property( $this->plugin, 'integrations_bootstrapped', false );

    $this->plugin->bootstrap_integrations();
    $this->plugin->bootstrap_integrations();

    $this->assertSame( 1, $registry->register_all_calls );
    $this->assertSame( [], $this->plugin->get_adapters() );
  }

  public function test_bootstrap_integrations_registers_no_adapters_without_dependencies() {
    $registry = new PWTSR_Test_Counting_Registry();

    $this->set_private_property( $this->plugin, 'adapter_registry', $registry );
    $this->set_private_property( $this->plugin, 'integrations_bootstrapped', false );

    $this->plugin->bootstrap_integrations();

    $this->assertSame( 1, $registry->register_all_calls );
    $this->assertCount( 0, $this->plugin->get_adapters() );
  }

  /**
   * Set a private property declared on the plugin class.
   *
   * @param object $object Target object.
   * @param string $name   Property name.
   * @param mixed  $value  Property value.
   */
  private function set_private_property( $object, $name, $value ) {
    $reflection = new ReflectionObject( $object );

    while ( $reflection ) {
      if ( $reflection->hasProperty( $name ) ) {
        $property = $reflection->getProperty( $name );
        $property->setAccessible( true );
        $property->setValue( $object, $value );

        return;
      }

      $reflection = $reflection->getParentClass();
    }

    $this->fail( sprintf( 'Property %s not found.', $name ) );
  }
}
