<?php
/**
 * Tests Contact Form 7 adapter behavior that does not require CF7 runtime.
 */

require_once dirname( __DIR__ ) . '/includes/traits/trait-adapter-assets.php';
require_once dirname( __DIR__ ) . '/includes/traits/trait-contact-form-7-adapter.php';

if ( ! class_exists( 'WPCF7_Submission' ) ) {
  class WPCF7_Submission {
    /** @var WPCF7_Submission|null */
    public static $instance;

    /** @var array */
    private $posted_data;

    public function __construct( $posted_data = [] ) {
      $this->posted_data = is_array( $posted_data ) ? $posted_data : [];
    }

    public static function get_instance() {
      return self::$instance;
    }

    public function get_posted_data() {
      return $this->posted_data;
    }
  }
}

class PWTSR_Test_CF7_Adapter_Harness {
  use PWTSR_Adapter_Assets_Trait;
  use PWTSR_Contact_Form_7_Trait;

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

class ContactForm7AdapterTest extends WP_UnitTestCase {
  /** @var PWTSR_Test_CF7_Adapter_Harness */
  protected $adapter;

  protected function setUp(): void {
    parent::setUp();

    $this->adapter = new PWTSR_Test_CF7_Adapter_Harness( new PWTSR_Tracking_Service() );
    delete_option( PWTSR::SETTINGS_KEY );

    WPCF7_Submission::$instance = null;
    $_POST = [];
  }

  protected function tearDown(): void {
    WPCF7_Submission::$instance = null;
    $_POST = [];

    parent::tearDown();
  }

  public function test_inject_tracking_inputs_appends_wrapper_once() {
    $html = '<form><input name="email" /></form>';

    $first = $this->adapter->inject_contact_form_7_tracking_inputs( $html );
    $second = $this->adapter->inject_contact_form_7_tracking_inputs( $first );

    $this->assertStringContainsString( 'data-presswell-transceiver-adapter="cf7"', $first );
    $this->assertStringContainsString( 'name="utm_source"', $first );
    $this->assertSame( $first, $second );
  }

  public function test_sanitize_posted_data_uses_posted_data_and_fallback_post_values() {
    $_POST = [
      'utm_source' => 'Google ',
      'referrer' => 'https://example.com/path?x=1',
      'utm_term' => [ 'bad' ],
    ];

    $result = $this->adapter->sanitize_contact_form_7_posted_data(
      [
        'utm_source' => '  Newsletter ',
      ]
    );

    $this->assertSame( 'Newsletter', $result['utm_source'] );
    $this->assertSame( 'https://example.com/path?x=1', $result['referrer'] );
    $this->assertArrayNotHasKey( 'utm_term', $result );
  }

  public function test_add_mail_tag_suggestions_includes_tracking_all_and_keys() {
    $tags = $this->adapter->add_contact_form_7_mail_tag_suggestions( [ 'existing' ] );

    $this->assertContains( 'existing', $tags );
    $this->assertContains( 'tracking-all', $tags );
    $this->assertContains( 'tracking-utm_source', $tags );
  }

  public function test_render_special_mail_tags_returns_tracking_values() {
    WPCF7_Submission::$instance = new WPCF7_Submission(
      [
        'utm_source' => 'google',
        'utm_campaign' => 'spring_launch',
      ]
    );

    $single = $this->adapter->render_contact_form_7_special_mail_tags( '', 'tracking-utm_source', false, null );
    $all = $this->adapter->render_contact_form_7_special_mail_tags( '', 'tracking-all', false, null );

    $this->assertSame( 'google', $single );
    $this->assertStringContainsString( 'utm_source: google', $all );
    $this->assertStringContainsString( 'utm_campaign: spring_launch', $all );
  }
}
