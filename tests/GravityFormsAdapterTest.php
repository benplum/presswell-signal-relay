<?php
/**
 * Tests Gravity Forms adapter logic that is independent of Gravity runtime.
 */

require_once dirname( __DIR__ ) . '/includes/traits/trait-gravity-forms-adapter.php';

class PWTSR_Test_Gravity_Adapter_Harness {
  use PWTSR_Gravity_Forms_Trait;

  /** @var PWTSR_Tracking_Service */
  public $service;

  /** @var bool */
  private $styles_enqueued = false;

  public function __construct( PWTSR_Tracking_Service $service ) {
    $this->service = $service;
  }
}

class GravityFormsAdapterTest extends WP_UnitTestCase {
  /** @var PWTSR_Test_Gravity_Adapter_Harness */
  protected $adapter;

  protected function setUp(): void {
    parent::setUp();

    $this->adapter = new PWTSR_Test_Gravity_Adapter_Harness( new PWTSR_Tracking_Service() );
    $_POST = [];
  }

  protected function tearDown(): void {
    $_POST = [];

    parent::tearDown();
  }

  public function test_enforce_single_tracking_field_removes_duplicates() {
    $form = [
      'fields' => [
        [ 'id' => 1, 'type' => 'text' ],
        [ 'id' => 2, 'type' => PWTSR::FIELD_TYPE ],
        [ 'id' => 3, 'type' => PWTSR::FIELD_TYPE ],
      ],
    ];

    $filtered = $this->adapter->enforce_single_tracking_field( $form );

    $types = wp_list_pluck( $filtered['fields'], 'type' );
    $this->assertSame( 1, count( array_keys( $types, PWTSR::FIELD_TYPE, true ) ) );
    $this->assertCount( 2, $filtered['fields'] );
  }

  public function test_sanitize_submission_values_updates_expected_input_keys() {
    $form = [
      'fields' => [
        [ 'id' => 12, 'type' => PWTSR::FIELD_TYPE ],
      ],
    ];

    $_POST['input_12_1'] = 'Google';
    $_POST['input_12_2'] = [ 'bad' ];

    $this->adapter->sanitize_tracking_submission_values( $form );

    $this->assertSame( 'Google', $_POST['input_12_1'] );
    $this->assertArrayNotHasKey( 'input_12_2', $_POST );
  }
}
