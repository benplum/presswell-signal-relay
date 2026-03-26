<?php
/**
 * Tests Forminator adapter behavior that can run without Forminator runtime.
 */

require_once dirname( __DIR__ ) . '/includes/traits/trait-adapter-assets.php';
require_once dirname( __DIR__ ) . '/includes/traits/trait-forminator-adapter.php';

class PWTSR_Test_Forminator_Entry {
  /** @var array<string,string> */
  private $meta = [];

  /** @var array */
  public $meta_data = [];

  /**
   * @param array<string,string> $meta Entry meta values.
   */
  public function __construct( array $meta = [] ) {
    $this->meta = $meta;

    foreach ( $meta as $key => $value ) {
      $this->meta_data[ $key ] = [ 'value' => $value ];
    }
  }

  public function get_meta( $key, $default = '' ) {
    return isset( $this->meta[ $key ] ) ? $this->meta[ $key ] : $default;
  }
}

class PWTSR_Test_Forminator_Adapter_Harness {
  use PWTSR_Adapter_Assets_Trait;
  use PWTSR_Forminator_Trait;

  /** @var PWTSR_Tracking_Service */
  public $service;

  /** @var bool */
  private $assets_registered = false;

  /** @var array */
  private $localized_objects = [];

  public function __construct( PWTSR_Tracking_Service $service ) {
    $this->service = $service;
  }
}

class ForminatorAdapterTest extends WP_UnitTestCase {
  /** @var PWTSR_Test_Forminator_Adapter_Harness */
  protected $adapter;

  protected function setUp(): void {
    parent::setUp();

    $this->adapter = new PWTSR_Test_Forminator_Adapter_Harness( new PWTSR_Tracking_Service() );
    $_POST = [];
  }

  protected function tearDown(): void {
    $_POST = [];

    parent::tearDown();
  }

  public function test_inject_hidden_tracking_inputs_appends_wrapper_once() {
    $html = '<form id="my-form"><input name="email" /></form>';

    $first = $this->adapter->inject_hidden_tracking_inputs( $html );
    $second = $this->adapter->inject_hidden_tracking_inputs( $first );

    $this->assertStringContainsString( 'data-presswell-transceiver-adapter="forminator"', $first );
    $this->assertStringContainsString( 'name="utm_source"', $first );
    $this->assertSame( $first, $second );
  }

  public function test_append_tracking_to_entry_reads_posted_values() {
    $_POST = [
      'utm_source' => ' Google ',
      'data' => [
        'utm_campaign' => ' spring_launch ',
      ],
    ];

    $result = $this->adapter->append_tracking_to_entry( [ [ 'name' => 'email', 'value' => 'a@example.com' ] ], 12 );

    $source_found = false;
    $campaign_found = false;

    foreach ( $result as $row ) {
      if ( isset( $row['name'] ) && 'utm_source' === $row['name'] && isset( $row['value'] ) && 'Google' === $row['value'] ) {
        $source_found = true;
      }

      if ( isset( $row['name'] ) && 'utm_campaign' === $row['name'] && isset( $row['value'] ) && 'spring_launch' === $row['value'] ) {
        $campaign_found = true;
      }
    }

    $this->assertTrue( $source_found );
    $this->assertTrue( $campaign_found );
  }

  public function test_append_tracking_to_entries_iterator_adds_non_duplicate_rows() {
    $iterator = [
      'detail' => [
        'items' => [
          [ 'label' => 'existing', 'value' => 'x' ],
          [ 'label' => 'utm_source', 'value' => 'already-there' ],
        ],
      ],
    ];

    $entry = new PWTSR_Test_Forminator_Entry(
      [
        'utm_source' => 'newsletter',
        'utm_campaign' => 'launch',
      ]
    );

    $result = $this->adapter->append_tracking_to_entries_iterator( $iterator, $entry );

    $labels = [];
    foreach ( $result['detail']['items'] as $item ) {
      $labels[] = isset( $item['label'] ) ? $item['label'] : '';
    }

    $this->assertSame( 1, count( array_keys( $labels, 'utm_source', true ) ) );
    $this->assertContains( 'utm_campaign', $labels );
  }

  public function test_replace_tracking_placeholders_uses_prepared_data_with_entry_fallback() {
    $entry = new PWTSR_Test_Forminator_Entry(
      [
        'utm_source' => 'entry-source',
        'utm_medium' => 'cpc',
      ]
    );

    $prepared_data = [
      'utm_source' => 'prepared-source',
    ];

    $content = 'All: {tracking_all} | Source: {tracking_utm_source} | Medium: {tracking_utm_medium}';

    $result = $this->adapter->replace_forminator_tracking_placeholders( $content, null, $prepared_data, $entry, [], [] );

    $this->assertStringContainsString( 'Source: prepared-source', $result );
    $this->assertStringContainsString( 'Medium: cpc', $result );
    $this->assertStringContainsString( 'utm_source: prepared-source', $result );
    $this->assertStringContainsString( 'utm_medium: cpc', $result );
  }
}
