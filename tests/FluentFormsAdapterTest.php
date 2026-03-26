<?php
/**
 * Tests Fluent Forms adapter logic that is independent of Fluent runtime.
 */

require_once dirname( __DIR__ ) . '/includes/traits/trait-fluent-forms-adapter.php';

class PWTSR_Test_Fluent_Parser {
  /** @var object|null */
  public static $entry;

  /** @var string */
  public static $provider = 'default';

  public static function getEntry() {
    return self::$entry;
  }

  public static function getProvider() {
    return self::$provider;
  }
}

class PWTSR_Test_Fluent_Adapter_Harness {
  use PWTSR_Fluent_Forms_Trait;

  /** @var PWTSR_Tracking_Service */
  public $service;

  public function __construct( PWTSR_Tracking_Service $service ) {
    $this->service = $service;
  }
}

class FluentFormsAdapterTest extends WP_UnitTestCase {
  /** @var PWTSR_Test_Fluent_Adapter_Harness */
  protected $adapter;

  protected function setUp(): void {
    parent::setUp();

    $this->adapter = new PWTSR_Test_Fluent_Adapter_Harness( new PWTSR_Tracking_Service() );
    PWTSR_Test_Fluent_Parser::$entry = null;
    PWTSR_Test_Fluent_Parser::$provider = 'default';
  }

  protected function tearDown(): void {
    PWTSR_Test_Fluent_Parser::$entry = null;
    PWTSR_Test_Fluent_Parser::$provider = 'default';

    parent::tearDown();
  }

  public function test_register_tracking_smartcodes_adds_group_and_tracking_keys() {
    $groups = $this->adapter->register_fluent_forms_tracking_smartcodes( [], null );

    $this->assertNotEmpty( $groups );
    $last_group = $groups[ count( $groups ) - 1 ];

    $this->assertSame( 'Tracking Signals', $last_group['title'] );
    $this->assertArrayHasKey( '{tracking.all}', $last_group['shortcodes'] );
    $this->assertArrayHasKey( '{tracking.utm_source}', $last_group['shortcodes'] );
  }

  public function test_resolve_smartcode_returns_specific_tracking_value() {
    PWTSR_Test_Fluent_Parser::$entry = (object) [
      'response' => wp_json_encode(
        [
          'pwtsr_tracking' => [
            'utm_source' => 'newsletter',
          ],
        ]
      ),
    ];

    $value = $this->adapter->resolve_fluent_forms_tracking_smartcode( 'utm_source', new PWTSR_Test_Fluent_Parser() );

    $this->assertSame( 'newsletter', $value );
  }

  public function test_resolve_smartcode_wraps_tracking_all_in_notification_context() {
    PWTSR_Test_Fluent_Parser::$entry = (object) [
      'response' => [
        'pwtsr_tracking' => [
          'utm_source' => 'google',
          'utm_medium' => 'cpc',
        ],
      ],
    ];
    PWTSR_Test_Fluent_Parser::$provider = 'notifications';

    $value = $this->adapter->resolve_fluent_forms_tracking_smartcode( 'all', new PWTSR_Test_Fluent_Parser() );

    $this->assertStringStartsWith( '[[PWTSR_TRACKING_ALL]]', $value );
    $this->assertStringContainsString( 'Utm Source: google', $value );
    $this->assertStringContainsString( 'Utm Medium: cpc', $value );
  }

  public function test_format_tracking_all_email_body_converts_token_content_to_html() {
    $body = "Intro\n[[PWTSR_TRACKING_ALL]]utm_source: google\nutm_medium: cpc[[/PWTSR_TRACKING_ALL]]\nEnd";

    $formatted = $this->adapter->format_fluent_forms_tracking_all_email_body(
      $body,
      [ 'asPlainText' => 'no' ],
      [],
      null
    );

    $this->assertStringContainsString( 'utm_source: google<br />utm_medium: cpc', $formatted );
    $this->assertStringNotContainsString( '[[PWTSR_TRACKING_ALL]]', $formatted );
  }

  public function test_format_tracking_all_email_subject_strips_markers_and_newlines() {
    $subject = "Lead [[PWTSR_TRACKING_ALL]]utm_source: google\nutm_medium: cpc[[/PWTSR_TRACKING_ALL]]";

    $formatted = $this->adapter->format_fluent_forms_tracking_all_email_subject( $subject, [], [], null );

    $this->assertStringNotContainsString( 'PWTSR_TRACKING_ALL', $formatted );
    $this->assertStringContainsString( 'Lead', $formatted );
    $this->assertStringContainsString( 'utm_source: google | utm_medium: cpc', $formatted );
  }

  public function test_normalize_submission_payload_moves_top_level_tracking_keys() {
    $submission = (object) [
      'response' => wp_json_encode(
        [
          'utm_source' => 'newsletter',
          'utm_medium' => 'email',
        ]
      ),
      'user_inputs' => [],
    ];

    $normalized = $this->adapter->normalize_fluent_forms_submission_tracking_payload( $submission, 0 );
    $decoded = json_decode( $normalized->response, true );

    $this->assertArrayHasKey( 'pwtsr_tracking', $decoded );
    $this->assertSame( 'newsletter', $decoded['pwtsr_tracking']['utm_source'] );
    $this->assertSame( 'email', $decoded['pwtsr_tracking']['utm_medium'] );
    $this->assertArrayNotHasKey( 'utm_source', $decoded );
    $this->assertArrayNotHasKey( 'utm_medium', $decoded );
    $this->assertStringContainsString( 'Utm Source: newsletter', $normalized->user_inputs['pwtsr_tracking'] );
  }
}
