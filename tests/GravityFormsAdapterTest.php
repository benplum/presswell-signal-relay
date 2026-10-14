<?php
/**
 * Tests Gravity Forms adapter logic that is independent of Gravity runtime.
 */

require_once dirname( __DIR__ ) . '/includes/traits/trait-gravity-forms-adapter.php';

if ( ! class_exists( 'GF_Field' ) ) {
  /**
   * Minimal stand-in for Gravity Forms' base field class.
   */
  class GF_Field {
    /** @var int */
    public $id = 0;

    /** @var array|null */
    public $inputs = null;
  }
}

if ( ! function_exists( 'rgar' ) ) {
  /**
   * Minimal stand-in for Gravity Forms' array getter.
   *
   * @param array  $array Source array.
   * @param string $name  Key.
   *
   * @return mixed
   */
  function rgar( $array, $name ) {
    return isset( $array[ $name ] ) ? $array[ $name ] : null;
  }
}

require_once dirname( __DIR__ ) . '/includes/adapters/class-gravity-forms-field.php';

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

  public function test_submission_filter_no_longer_depends_on_a_nonce() {
    $this->assertFalse( method_exists( $this->adapter, 'sanitize_tracking_submission_values' ) );
  }

  public function test_field_sanitizes_values_on_save_without_a_nonce() {
    $field = $this->make_field();

    $this->assertSame( 'Google', $field->get_value_save_input( ' <b>Google</b> ', [], 'input_12_1', 0, [] ) );
    $this->assertSame( '', $field->get_value_save_input( [ 'bad' ], [], 'input_12_1', 0, [] ) );
    $this->assertSame( 'a b', $field->get_value_save_entry( "a\nb", [], 'input_12_1', 0, [] ) );
  }

  public function test_field_applies_url_rules_by_stored_input_key() {
    $field = $this->make_field();

    $this->assertSame( 'https://example.com/a%20b?q=red%20shoes', $field->get_value_save_input( 'https://example.com/a%20b?q=red%20shoes', [], 'input_12_2', 0, [] ) );
    $this->assertSame( '', $field->get_value_save_input( 'javascript:alert(1)', [], 'input_12_2', 0, [] ) );
  }

  public function test_field_drops_values_for_unknown_inputs() {
    $field = $this->make_field();

    $this->assertSame( '', $field->get_value_save_input( 'Google', [], 'input_12_99', 0, [] ) );
    $this->assertSame( '', $field->get_value_save_input( 'Google', [], 'not_an_input', 0, [] ) );
  }

  /**
   * Build a tracking field with inputs shaped like the form editor saves them.
   *
   * @return PWTSR_Gravity_Forms_Field
   */
  private function make_field() {
    $field         = new PWTSR_Gravity_Forms_Field();
    $field->id     = 12;
    $field->inputs = [
      [ 'id' => '12.1', 'label' => 'utm_source', 'name' => '' ],
      [ 'id' => '12.2', 'label' => 'referrer', 'name' => 'referrer' ],
    ];

    return $field;
  }

  public function test_input_suffixes_skip_multiples_of_ten() {
    $this->assertSame( [ 1, 2, 3, 4, 5, 6, 7, 8, 9, 11, 12 ], PWTSR_Gravity_Forms_Field::get_input_suffixes( 11 ) );
  }

  public function test_entry_inputs_keep_stored_ids_and_append_new_keys() {
    $inputs = $this->make_field()->get_entry_inputs();
    $by_key = wp_list_pluck( $inputs, 'id', 'label' );

    $this->assertSame( '12.1', $by_key['utm_source'] );
    $this->assertSame( '12.2', $by_key['referrer'] );
    $this->assertArrayHasKey( 'first_utm_source', $by_key );
    $this->assertCount( count( presswell_tracking_signal_relay()->get_service()->get_tracking_keys( PWTSR::ADAPTER_GRAVITY_FORMS ) ), $inputs );
    $this->assertNotContains( '12.10', array_values( $by_key ) );
    $this->assertSame( count( $inputs ), count( array_unique( wp_list_pluck( $inputs, 'id' ) ) ) );
  }

  public function test_entry_pairs_are_read_by_stored_key_not_position() {
    $field = $this->make_field();
    $field->inputs = [
      [ 'id' => '12.1', 'label' => 'referrer', 'name' => 'referrer' ],
      [ 'id' => '12.2', 'label' => 'utm_source', 'name' => '' ],
    ];

    $pairs = $field->get_entry_tracking_pairs(
      [
        '12.1' => 'https://example.com/',
        '12.2' => 'google',
      ]
    );

    $this->assertSame( 'https://example.com/', $pairs['referrer'] );
    $this->assertSame( 'google', $pairs['utm_source'] );
  }

  public function test_merge_tags_are_escaped_in_html_format_even_without_esc_html() {
    $field = $this->make_field();
    $form  = [ 'id' => 42, 'fields' => [ $field ] ];
    $entry = [ '12.1' => 'a"b & c' ];

    $html = $this->adapter->replace_tracking_merge_tags( '<a title="{tracking:utm_source}">x</a>', $form, $entry, false, false, false, 'html' );
    $text = $this->adapter->replace_tracking_merge_tags( '{tracking:utm_source}', $form, $entry, false, false, false, 'text' );

    $this->assertSame( '<a title="a&quot;b &amp; c">x</a>', $html );
    $this->assertSame( 'a"b & c', $text );
  }

  public function test_entry_list_shows_one_value_per_input_column_and_a_summary_for_the_field() {
    $field = $this->make_field();
    $entry = [ '12.1' => 'google', '12.2' => 'https://example.com/' ];

    $this->assertSame( 'google', $field->get_value_entry_list( '', $entry, '12.1', [], [] ) );
    $this->assertSame( 'https://example.com/', $field->get_value_entry_list( '', $entry, '12.2', [], [] ) );
    $this->assertSame( 'utm_source: google, referrer: https://example.com/', $field->get_value_entry_list( '', $entry, 12, [], [] ) );
  }
}
