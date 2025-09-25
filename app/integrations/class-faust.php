<?php
/**
 * Faust.js integration class
 *
 * This class adds compatibility with the Faust.js headless WordPress framework.
 * Faust.js is a framework for building headless WordPress applications with frontend
 * frameworks like Next.js, Nuxt.js, etc.
 *
 * @link https://vcore.au
 *
 * @package CF_Images
 * @subpackage CF_Images/App/Integrations
 * @author Anton Vanyukov <a.vanyukov@vcore.ru>
 * @since 1.9.6
 */

namespace CF_Images\App\Integrations;

if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Faust class.
 *
 * @since 1.9.6
 */
class Faust extends Integration {
	/**
	 * Check if the integration should run.
	 *
	 * Faust.js integration should run when:
	 * 1. Faust.js plugin is active, or
	 * 2. Headless mode is manually enabled via filter or constant
	 *
	 * @since 1.9.6
	 *
	 * @return bool
	 */
	protected function should_run(): bool {
		// Check if Faust.js plugin is active
		if ( function_exists( 'is_plugin_active' ) ) {
			if ( is_plugin_active( 'faustwp/faustwp.php' ) ) {
				return true;
			}
		} else {
			// Fallback check if is_plugin_active is not available
			if ( class_exists( 'WPE\FaustWP\Settings\FaustSettings' ) ) {
				return true;
			}
		}

		// Check for manual headless mode activation
		if ( defined( 'CF_IMAGES_HEADLESS_MODE' ) && CF_IMAGES_HEADLESS_MODE ) {
			return true;
		}

		// Allow activation via filter
		return apply_filters( 'cf_images_faust_integration_active', false );
	}

	/**
	 * Define the variables for the integration.
	 *
	 * @since 1.9.6
	 */
	protected function init() {
		$this->name = esc_html__( 'Faust.js / Headless WordPress', 'cf-images' );
		$this->slug = 'faust';

		// Enable image processing for REST API requests when in headless mode
		add_filter( 'cf_images_is_rest_request', array( $this, 'maybe_allow_rest_requests' ), 10, 2 );

		// Add support for GraphQL if WPGraphQL is active
		if ( class_exists( 'WPGraphQL' ) ) {
			add_filter( 'graphql_request_data', array( $this, 'process_graphql_images' ), 10, 1 );
		}

		// Hook into REST API responses to ensure images are processed
		add_filter( 'rest_prepare_attachment', array( $this, 'process_attachment_rest_response' ), 10, 3 );
		add_filter( 'rest_prepare_post', array( $this, 'process_post_rest_response' ), 10, 3 );
		add_filter( 'rest_prepare_page', array( $this, 'process_post_rest_response' ), 10, 3 );

		// Handle ACF fields in REST API if ACF is active
		if ( class_exists( 'ACF' ) ) {
			add_filter( 'acf/format_value', array( $this, 'process_acf_image_fields' ), 99, 3 );
		}
	}

	/**
	 * Define the integration options.
	 *
	 * @since 1.9.6
	 *
	 * @param array  $options Integration options.
	 * @param string $slug    Integration slug.
	 *
	 * @return array
	 */
	public function integration_options( array $options, string $slug ): array {
		if ( $this->slug !== $slug ) {
			return $options;
		}

		return array(
			array(
				'name'        => 'rest_api_support',
				'label'       => esc_html__( 'Process images in REST API', 'cf-images' ),
				'description' => esc_html__( 'Enable image processing for REST API requests. This is essential for headless setups.', 'cf-images' ),
				'value'       => apply_filters( 'cf_images_integration_option_value', true, 'rest_api_support' ),
			),
			array(
				'name'        => 'graphql_support',
				'label'       => esc_html__( 'Process images in GraphQL responses', 'cf-images' ),
				'description' => esc_html__( 'Enable image processing for GraphQL responses when WPGraphQL is active.', 'cf-images' ),
				'value'       => apply_filters( 'cf_images_integration_option_value', true, 'graphql_support' ),
			),
			array(
				'name'        => 'acf_fields_support',
				'label'       => esc_html__( 'Process ACF image fields', 'cf-images' ),
				'description' => esc_html__( 'Process image URLs in Advanced Custom Fields when exposed via REST API or GraphQL.', 'cf-images' ),
				'value'       => apply_filters( 'cf_images_integration_option_value', true, 'acf_fields_support' ),
			),
			array(
				'name'        => 'content_processing',
				'label'       => esc_html__( 'Process content images', 'cf-images' ),
				'description' => esc_html__( 'Process images within post content for headless rendering.', 'cf-images' ),
				'value'       => apply_filters( 'cf_images_integration_option_value', true, 'content_processing' ),
			),
		);
	}

	/**
	 * Allow REST API requests when headless mode is enabled.
	 *
	 * @since 1.9.6
	 *
	 * @param bool $is_rest_request Current REST request status.
	 * @param int  $attachment_id   Attachment ID.
	 *
	 * @return bool
	 */
	public function maybe_allow_rest_requests( bool $is_rest_request, int $attachment_id ): bool {
		// If not a REST request, no need to change anything
		if ( ! $is_rest_request ) {
			return $is_rest_request;
		}

		// Check if REST API support is enabled
		if ( ! $this->integration_option_value( true, 'rest_api_support' ) ) {
			return $is_rest_request;
		}

		// Allow REST requests for headless mode
		return false;
	}

	/**
	 * Process attachment data in REST API responses.
	 *
	 * @since 1.9.6
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_Post          $post     Post object.
	 * @param \WP_REST_Request  $request  Request used to generate the response.
	 *
	 * @return \WP_REST_Response
	 */
	public function process_attachment_rest_response( $response, $post, $request ) {
		if ( ! $this->integration_option_value( true, 'rest_api_support' ) ) {
			return $response;
		}

		$data = $response->get_data();

		// Process media_details if available
		if ( isset( $data['media_details']['sizes'] ) && is_array( $data['media_details']['sizes'] ) ) {
			foreach ( $data['media_details']['sizes'] as $size_name => $size_data ) {
				if ( isset( $size_data['source_url'] ) ) {
					// Process the image URL through CF Images
					$processed_url = apply_filters( 'wp_get_attachment_image_src', array( $size_data['source_url'] ), $post->ID, $size_name );
					if ( ! empty( $processed_url[0] ) ) {
						$data['media_details']['sizes'][ $size_name ]['source_url'] = $processed_url[0];
					}
				}
			}
		}

		// Process main source_url
		if ( isset( $data['source_url'] ) ) {
			$processed_url = apply_filters( 'wp_get_attachment_url', $data['source_url'], $post->ID );
			if ( ! empty( $processed_url ) ) {
				$data['source_url'] = $processed_url;
			}
		}

		$response->set_data( $data );
		return $response;
	}

	/**
	 * Process post content and featured images in REST API responses.
	 *
	 * @since 1.9.6
	 *
	 * @param \WP_REST_Response $response The response object.
	 * @param \WP_Post          $post     Post object.
	 * @param \WP_REST_Request  $request  Request used to generate the response.
	 *
	 * @return \WP_REST_Response
	 */
	public function process_post_rest_response( $response, $post, $request ) {
		if ( ! $this->integration_option_value( true, 'content_processing' ) ) {
			return $response;
		}

		$data = $response->get_data();

		// Process content images
		if ( isset( $data['content']['rendered'] ) ) {
			$data['content']['rendered'] = $this->process_content_images( $data['content']['rendered'] );
		}

		// Process excerpt images
		if ( isset( $data['excerpt']['rendered'] ) ) {
			$data['excerpt']['rendered'] = $this->process_content_images( $data['excerpt']['rendered'] );
		}

		$response->set_data( $data );
		return $response;
	}

	/**
	 * Process images within content.
	 *
	 * @since 1.9.6
	 *
	 * @param string $content HTML content.
	 *
	 * @return string
	 */
	private function process_content_images( string $content ): string {
		// Use the same image processing as the main plugin
		$pattern = '/<img[^>]+>/i';
		return preg_replace_callback( $pattern, array( $this, 'process_img_tag' ), $content );
	}

	/**
	 * Process individual img tags.
	 *
	 * @since 1.9.6
	 *
	 * @param array $matches Regex matches.
	 *
	 * @return string
	 */
	private function process_img_tag( array $matches ): string {
		$img_tag = $matches[0];

		// Extract attachment ID if available
		$attachment_id = 0;
		if ( preg_match( '/wp-image-(\d+)/', $img_tag, $id_matches ) ) {
			$attachment_id = (int) $id_matches[1];
		}

		// Apply the same filter that the main plugin uses
		return apply_filters( 'wp_content_img_tag', $img_tag, '', $attachment_id );
	}

	/**
	 * Process ACF image fields for headless compatibility.
	 *
	 * @since 1.9.6
	 *
	 * @param mixed $value   The field value.
	 * @param int   $post_id The post ID where the value is saved.
	 * @param array $field   The field array containing all field settings.
	 *
	 * @return mixed
	 */
	public function process_acf_image_fields( $value, $post_id, $field ) {
		if ( ! $this->integration_option_value( true, 'acf_fields_support' ) ) {
			return $value;
		}

		// Only process image fields
		if ( ! isset( $field['type'] ) || 'image' !== $field['type'] ) {
			return $value;
		}

		// Process image array format
		if ( is_array( $value ) && isset( $value['url'] ) ) {
			$attachment_id = isset( $value['ID'] ) ? $value['ID'] : 0;
			$processed_url = apply_filters( 'wp_get_attachment_url', $value['url'], $attachment_id );
			
			if ( ! empty( $processed_url ) ) {
				$value['url'] = $processed_url;
			}

			// Process sizes if available
			if ( isset( $value['sizes'] ) && is_array( $value['sizes'] ) ) {
				foreach ( $value['sizes'] as $size_name => $size_url ) {
					$processed_size_url = apply_filters( 'wp_get_attachment_image_src', array( $size_url ), $attachment_id, $size_name );
					if ( ! empty( $processed_size_url[0] ) ) {
						$value['sizes'][ $size_name ] = $processed_size_url[0];
					}
				}
			}
		}

		// Process URL format
		if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
			$processed_url = apply_filters( 'wp_get_attachment_url', $value, 0 );
			if ( ! empty( $processed_url ) ) {
				$value = $processed_url;
			}
		}

		return $value;
	}

	/**
	 * Process GraphQL responses when WPGraphQL is active.
	 *
	 * @since 1.9.6
	 *
	 * @param array $response_data GraphQL response data.
	 *
	 * @return array
	 */
	public function process_graphql_images( array $response_data ): array {
		if ( ! $this->integration_option_value( true, 'graphql_support' ) ) {
			return $response_data;
		}

		// This would need more specific implementation based on GraphQL schema
		// For now, we'll add a hook that allows other plugins to process GraphQL data
		return apply_filters( 'cf_images_process_graphql_data', $response_data, $this );
	}
}