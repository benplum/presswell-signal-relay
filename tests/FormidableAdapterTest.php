<?php
/**
 * Tests Formidable adapter behavior that can run without Formidable runtime.
 */

require_once dirname( __DIR__ ) . '/includes/traits/trait-formidable-adapter.php';

if ( ! class_exists( 'FrmField' ) ) {
  class FrmField {
    /** @var array<int,array<int,object>> */
    public static $fields_by_form = [];

    public static function get_all_types_in_form( $form_id, $type ) {
      $form_id = absint( $form_id );

      return isset( self::$fields_by_form[ $form_id ] ) ? self::$fields_by_form[ $form_id ] : [];
    }
  }
}

if ( ! class_exists( 'FrmEntryMeta' ) ) {
  class FrmEntryMeta {
    /** @var array<string,mixed> */
    public static $meta = [];

    public static function get_entry_meta_by_field( $entry_id, $field_id ) {
      $key = absint( $entry_id ) . ':' . absint( $field_id );

      return isset( self::$meta[ $key ] ) ? self::$meta[ $key ] : '';
    }
  }
}

if ( ! class_exists( 'PWTSR_Formidable_Field' ) ) {
  class PWTSR_Formidable_Field {}
}

class PWTSR_Test_Formidable_Adapter_Harness {
  use PWTSR_Formidable_Trait;

  /** @var PWTSR_Tracking_Service */
  public $service;

  public function __construct( PWTSR_Tracking_Service $service ) {
    $this->service = $service;
  }
}

class FormidableAdapterTest extends WP_UnitTestCase {
  /** @var PWTSR_Test_Formidable_Adapter_Harness */
  protected $adapter;

  protected function setUp(): void {
    parent::setUp();

    $this->adapter = new PWTSR_Test_Formidable_Adapter_Harness( new PWTSR_Tracking_Service() );
    FrmField::$fields_by_form = [];
    FrmEntryMeta::$meta = [];
    $_POST = [];
  }

  protected function tearDown(): void {
    FrmField::$fields_by_form = [];
    FrmEntryMeta::$meta = [];
    $_POST = [];

    parent::tearDown();
  }

  public function test_register_formidable_transceiver_field_type_adds_tracking_entry() {
    $fields = $this->adapter->register_formidable_transceiver_field_type( [] );

    $this->assertArrayHasKey( PWTSR::FIELD_TYPE, $fields );
    $this->assertSame( 'frmfont pwtsr-radar-icon', $fields[ PWTSR::FIELD_TYPE ]['icon'] );
  }

  public function test_register_formidable_transceiver_field_class_maps_to_custom_class() {
    $class = $this->adapter->register_formidable_transceiver_field_class( 'Other_Class', PWTSR::FIELD_TYPE );

    $this->assertSame( 'PWTSR_Formidable_Field', $class );
  }

  public function test_sanitize_formidable_submission_values_normalizes_tracking_pairs() {
    FrmField::$fields_by_form[123] = [ (object) [ 'id' => 10 ] ];

    $values = [
      'form_id' => 123,
      'item_meta' => [
        10 => [
          'utm_source' => ' Google ',
          'utm_campaign' => ' spring_launch ',
          'utm_term' => [ 'invalid' ],
        ],
      ],
    ];

    $result = $this->adapter->sanitize_formidable_submission_values( $values );

    $this->assertArrayHasKey( 10, $result['item_meta'] );
    $this->assertSame( 'Google', $result['item_meta'][10]['utm_source'] );
    $this->assertSame( 'spring_launch', $result['item_meta'][10]['utm_campaign'] );
    $this->assertArrayNotHasKey( 'utm_term', $result['item_meta'][10] );
  }

  public function test_format_formidable_tracking_display_value_returns_lines() {
    $field = (object) [ 'type' => PWTSR::FIELD_TYPE ];

    $text = $this->adapter->format_formidable_tracking_display_value(
      [
        'utm_source' => 'newsletter',
        'utm_medium' => 'email',
      ],
      $field,
      []
    );

    $html = $this->adapter->format_formidable_tracking_display_value(
      [
        'utm_source' => 'newsletter',
      ],
      $field,
      [ 'html' => true ]
    );

    $this->assertStringContainsString( "utm_source: newsletter", $text );
    $this->assertStringContainsString( "utm_medium: email", $text );
    $this->assertStringContainsString( 'utm_source: newsletter', $html );
  }

  public function test_replace_formidable_tracking_tokens_resolves_all_and_key_tokens() {
    FrmField::$fields_by_form[321] = [ (object) [ 'id' => 44 ] ];
    FrmEntryMeta::$meta['55:44'] = wp_json_encode(
      [
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
      ]
    );

    $entry = (object) [
      'id' => 55,
      'form_id' => 321,
    ];

    $content = 'ALL:[tracking_all] SOURCE:{tracking_utm_source} MEDIUM:[tracking_utm_medium]';

    $result = $this->adapter->replace_formidable_tracking_tokens( $content, 321, $entry );

    $this->assertStringContainsString( 'utm_source: google', $result );
    $this->assertStringContainsString( 'utm_medium: cpc', $result );
    $this->assertStringContainsString( 'SOURCE:google', $result );
    $this->assertStringContainsString( 'MEDIUM:cpc', $result );
  }
}
