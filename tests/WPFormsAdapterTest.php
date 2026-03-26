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

  public function test_sanitize_submission_values_builds_tracking_pairs_and_string_value() {
    $form_data = [
      'fields' => [
        7 => [
          'id' => 7,
          'type' => PWTSR::FIELD_TYPE,
        ],
      ],
    ];

    $fields = [
      7 => [
        'tracking' => [
          'utm_source' => ' Google ',
          'utm_campaign' => '  spring_launch',
        ],
      ],
    ];

    $sanitized = $this->adapter->sanitize_wpforms_tracking_submission_values( $fields, [], $form_data );

    $this->assertArrayHasKey( 'tracking', $sanitized[7] );
    $this->assertSame( 'Google', $sanitized[7]['tracking']['utm_source'] );
    $this->assertSame( 'spring_launch', $sanitized[7]['tracking']['utm_campaign'] );
    $this->assertStringContainsString( 'utm_source: Google', $sanitized[7]['value'] );
    $this->assertStringContainsString( 'utm_campaign: spring_launch', $sanitized[7]['value'] );
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
}
