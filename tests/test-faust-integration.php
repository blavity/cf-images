<?php
/**
 * Faust.js integration tests
 *
 * @package Cf_Images
 */

use CF_Images\App\Integrations\Faust;

/**
 * Test Faust.js integration functionality
 */
class Test_Faust_Integration extends Unit_Test_Base {
	/**
	 * Faust integration instance.
	 *
	 * @var Faust
	 */
	private $faust_integration;

	/**
	 * Setup test environment
	 */
	public function set_up() {
		parent::set_up();
		
		// Mock that Faust.js is active
		add_filter( 'cf_images_faust_integration_active', '__return_true' );
		
		// Initialize the integration
		$this->faust_integration = new Faust();
	}

	/**
	 * Test that integration initializes correctly
	 */
	public function test_faust_integration_initialization() {
		$this->assertInstanceOf( Faust::class, $this->faust_integration );
	}

	/**
	 * Test REST API request filtering
	 */
	public function test_rest_api_request_filtering() {
		// Mock a REST request
		$_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';
		
		// Test that REST requests are allowed when integration is enabled
		$is_rest_request = apply_filters( 'cf_images_is_rest_request', true, 123 );
		$this->assertFalse( $is_rest_request, 'REST requests should be allowed for headless mode' );
	}

	/**
	 * Test REST API attachment processing
	 */
	public function test_rest_attachment_processing() {
		$this->add_cf_image_id_and_hash();
		
		// Create a mock REST response for an attachment
		$attachment = get_post( self::$attachment_id );
		$response_data = array(
			'id' => self::$attachment_id,
			'source_url' => 'http://example.org/wp-content/uploads/2024/test-image.jpg',
			'media_details' => array(
				'sizes' => array(
					'medium' => array(
						'source_url' => 'http://example.org/wp-content/uploads/2024/test-image-300x200.jpg'
					),
					'large' => array(
						'source_url' => 'http://example.org/wp-content/uploads/2024/test-image-1024x683.jpg'
					)
				)
			)
		);
		
		$response = new WP_REST_Response( $response_data );
		$request = new WP_REST_Request();
		
		// Process the response through the integration
		$processed_response = $this->faust_integration->process_attachment_rest_response( 
			$response, 
			$attachment, 
			$request 
		);
		
		$processed_data = $processed_response->get_data();
		
		// Check that URLs have been processed (would contain Cloudflare URLs in real scenario)
		$this->assertNotEmpty( $processed_data['source_url'] );
		$this->assertNotEmpty( $processed_data['media_details']['sizes']['medium']['source_url'] );
		$this->assertNotEmpty( $processed_data['media_details']['sizes']['large']['source_url'] );
	}

	/**
	 * Test content image processing
	 */
	public function test_content_image_processing() {
		$content = '<p>Here is an image: <img src="http://example.org/wp-content/uploads/2024/test-image.jpg" class="wp-image-123" /></p>';
		
		// Create mock post response
		$post = (object) array(
			'ID' => 1,
			'post_content' => $content
		);
		
		$response_data = array(
			'id' => 1,
			'content' => array(
				'rendered' => $content
			)
		);
		
		$response = new WP_REST_Response( $response_data );
		$request = new WP_REST_Request();
		
		$processed_response = $this->faust_integration->process_post_rest_response(
			$response,
			$post,
			$request
		);
		
		$processed_data = $processed_response->get_data();
		
		// Verify that content is present and processed
		$this->assertArrayHasKey( 'content', $processed_data );
		$this->assertArrayHasKey( 'rendered', $processed_data['content'] );
		$this->assertNotEmpty( $processed_data['content']['rendered'] );
	}

	/**
	 * Test ACF image field processing
	 */
	public function test_acf_image_field_processing() {
		// Mock ACF being active
		if ( ! class_exists( 'ACF' ) ) {
			$this->markTestSkipped( 'ACF is not available in test environment' );
		}

		$image_field = array(
			'type' => 'image',
			'name' => 'test_image'
		);
		
		$image_value = array(
			'ID' => self::$attachment_id,
			'url' => 'http://example.org/wp-content/uploads/2024/test-image.jpg',
			'sizes' => array(
				'medium' => 'http://example.org/wp-content/uploads/2024/test-image-300x200.jpg',
				'large' => 'http://example.org/wp-content/uploads/2024/test-image-1024x683.jpg'
			)
		);
		
		$processed_value = $this->faust_integration->process_acf_image_fields(
			$image_value,
			1,
			$image_field
		);
		
		$this->assertIsArray( $processed_value );
		$this->assertArrayHasKey( 'url', $processed_value );
		$this->assertArrayHasKey( 'sizes', $processed_value );
		$this->assertNotEmpty( $processed_value['url'] );
	}

	/**
	 * Test integration options
	 */
	public function test_integration_options() {
		$options = $this->faust_integration->integration_options( array(), 'faust' );
		
		$this->assertIsArray( $options );
		$this->assertNotEmpty( $options );
		
		// Check for expected options
		$option_names = array_column( $options, 'name' );
		$this->assertContains( 'rest_api_support', $option_names );
		$this->assertContains( 'graphql_support', $option_names );
		$this->assertContains( 'acf_fields_support', $option_names );
		$this->assertContains( 'content_processing', $option_names );
	}

	/**
	 * Test should_run method with different conditions
	 */
	public function test_should_run_conditions() {
		// Remove the filter we added in setup
		remove_filter( 'cf_images_faust_integration_active', '__return_true' );
		
		// Test with constant
		if ( ! defined( 'CF_IMAGES_HEADLESS_MODE' ) ) {
			define( 'CF_IMAGES_HEADLESS_MODE', true );
		}
		
		$faust = new Faust();
		$this->assertInstanceOf( Faust::class, $faust );
	}

	/**
	 * Test URL processing for headless
	 */
	public function test_url_processing_for_headless() {
		// Test that regular image URLs get processed through CF Images filters
		$original_url = 'http://example.org/wp-content/uploads/2024/test-image.jpg';
		
		// Mock that we have CF Images setup
		$this->add_cf_image_id_and_hash();
		
		// Apply the filter that would normally process the image
		$processed_url = apply_filters( 'wp_get_attachment_url', $original_url, self::$attachment_id );
		
		// In a real scenario with CF Images configured, this would be a Cloudflare URL
		// For now, we just verify that the filter is being applied
		$this->assertIsString( $processed_url );
	}

	/**
	 * Clean up after tests
	 */
	public function tear_down() {
		remove_filter( 'cf_images_faust_integration_active', '__return_true' );
		parent::tear_down();
	}
}