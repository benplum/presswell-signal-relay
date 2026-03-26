<?php
/**
 * Tests adapter registry behavior.
 */

class PWTSR_Test_Adapter_Stub implements PWTSR_Form_Adapter_Interface {
  /** @var string */
  private $key;

  /** @var int */
  public $register_calls = 0;

  public function __construct( $key ) {
    $this->key = (string) $key;
  }

  public function key() {
    return $this->key;
  }

  public function register() {
    $this->register_calls++;
  }
}

class AdapterRegistryTest extends WP_UnitTestCase {
  public function test_add_skips_empty_keys_and_stores_by_sanitized_key() {
    $registry = new PWTSR_Adapter_Registry();

    $registry->add( new PWTSR_Test_Adapter_Stub( '' ) );
    $registry->add( new PWTSR_Test_Adapter_Stub( 'Fluent Forms!!' ) );

    $all = $registry->all();

    $this->assertCount( 1, $all );
    $this->assertArrayHasKey( 'fluentforms', $all );
    $this->assertInstanceOf( 'PWTSR_Form_Adapter_Interface', $all['fluentforms'] );
  }

  public function test_add_overwrites_existing_adapter_with_same_key() {
    $registry = new PWTSR_Adapter_Registry();

    $first = new PWTSR_Test_Adapter_Stub( 'gravityforms' );
    $second = new PWTSR_Test_Adapter_Stub( 'gravityforms' );

    $registry->add( $first );
    $registry->add( $second );

    $all = $registry->all();

    $this->assertCount( 1, $all );
    $this->assertSame( $second, $all['gravityforms'] );
  }

  public function test_register_all_calls_register_on_each_adapter() {
    $registry = new PWTSR_Adapter_Registry();

    $cf7 = new PWTSR_Test_Adapter_Stub( 'contactform7' );
    $wpforms = new PWTSR_Test_Adapter_Stub( 'wpforms' );

    $registry->add( $cf7 );
    $registry->add( $wpforms );
    $registry->register_all();

    $this->assertSame( 1, $cf7->register_calls );
    $this->assertSame( 1, $wpforms->register_calls );
  }
}
