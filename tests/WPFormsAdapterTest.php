<?php
/**
 * Tests WPForms adapter logic without requiring WPForms runtime classes.
 */

require_once dirname( __DIR__ ) . '/includes/traits/trait-wpforms-adapter.php';

class PWTSR_Test_WPForms_Adapter_Harness {
  use PWTSR_WPForms_Trait;

  /** @var PWTSR_Tracking_Service */
  public $service;

  public function __construct( PWTSR_Tracking_Service $service ) {
    $this->service = $service;
  }
}

class WPFormsAdapterTest extends WP_UnitTestCase {
  /** @var PWTSR_Test_WPForms_Adapter_Harness */
  protected $adapter;

  protected function setUp(): void {
    parent::setUp();

    $this->adapter = new PWTSR_Test_WPForms_Adapter_Harness( new PWTSR_Tracking_Service() );
  }

  public function test_smart_tags_sanitize_stored_tracking_values() {
    $form_data = [ 'fields' => [ 7 => [ 'id' => 7, 'type' => PWTSR::FIELD_TYPE ] ] ];
    $fields    = [
      7 => [
        'tracking' => [
          'utm_source' => ' <b>Google</b> ',
          'injected'   => 'not a tracking key',
        ],
      ],
    ];

    $this->assertSame( 'Google', $this->adapter->replace_wpforms_tracking_smart_tags( '', 'tracking_utm_source', $form_data, $fields ) );
    $this->assertSame( 'utm_source: Google', $this->adapter->replace_wpforms_tracking_smart_tags( '', 'tracking_all', $form_data, $fields ) );
  }

  public function test_replace_tracking_smart_tags_returns_specific_and_all_values() {
    $form_data = [
      'fields' => [
        11 => [
          'id' => 11,
          'type' => PWTSR::FIELD_TYPE,
        ],
      ],
    ];

    $fields = [
      11 => [
        'tracking' => [
          'utm_source' => 'newsletter',
          'utm_medium' => 'email',
        ],
      ],
    ];

    $single = $this->adapter->replace_wpforms_tracking_smart_tags( '', 'tracking_utm_source', $form_data, $fields, 0, null, 'email' );
    $all = $this->adapter->replace_wpforms_tracking_smart_tags( '', 'tracking_all', $form_data, $fields, 0, null, 'email' );

    $this->assertSame( 'newsletter', $single );
    $this->assertStringContainsString( 'utm_source: newsletter', $all );
    $this->assertStringContainsString( 'utm_medium: email', $all );
  }

  public function test_replace_tracking_smart_tags_accepts_six_arguments_from_older_wpforms() {
    $form_data = [ 'fields' => [ 11 => [ 'id' => 11, 'type' => PWTSR::FIELD_TYPE ] ] ];
    $fields    = [ 11 => [ 'tracking' => [ 'utm_source' => 'newsletter' ] ] ];

    $value = call_user_func_array(
      [ $this->adapter, 'replace_wpforms_tracking_smart_tags' ],
      [ '', 'tracking_utm_source', $form_data, $fields, 0, null ]
    );

    $this->assertSame( 'newsletter', $value );
  }
}
