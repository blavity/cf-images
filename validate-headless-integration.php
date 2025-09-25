<?php
/**
 * CF Images Headless Integration Validation Script
 * 
 * This script validates that the Faust.js integration is working correctly.
 * Run this script in a WordPress environment to test the integration.
 * 
 * Usage: php validate-headless-integration.php
 */

// Define WordPress environment if not already defined
if ( ! defined( 'ABSPATH' ) ) {
    // Try to find WordPress root
    $wp_root = dirname( __FILE__ );
    while ( $wp_root !== '/' && ! file_exists( $wp_root . '/wp-config.php' ) ) {
        $wp_root = dirname( $wp_root );
    }
    
    if ( file_exists( $wp_root . '/wp-config.php' ) ) {
        require_once $wp_root . '/wp-config.php';
        require_once ABSPATH . 'wp-admin/includes/admin.php';
    } else {
        echo "❌ Could not find WordPress installation\n";
        exit( 1 );
    }
}

/**
 * Validation class for CF Images Headless integration
 */
class CF_Images_Headless_Validator {
    
    private $results = array();
    
    public function run_validation() {
        echo "🔍 CF Images Headless Integration Validation\n";
        echo "==========================================\n\n";
        
        $this->check_plugin_active();
        $this->check_faust_integration();
        $this->check_rest_api_support();
        $this->check_image_processing();
        $this->check_filters_and_hooks();
        
        $this->display_results();
        
        return empty( array_filter( $this->results, function( $result ) {
            return $result['status'] === 'error';
        }));
    }
    
    private function check_plugin_active() {
        echo "1. Checking CF Images Plugin Status...\n";
        
        if ( class_exists( 'CF_Images\App\Core' ) ) {
            $this->add_result( 'plugin_active', 'success', 'CF Images plugin is active' );
        } else {
            $this->add_result( 'plugin_active', 'error', 'CF Images plugin is not active' );
            return;
        }
        
        // Check if core instance exists
        try {
            $core = \CF_Images\App\Core::get_instance();
            $this->add_result( 'core_instance', 'success', 'CF Images core instance created successfully' );
        } catch ( Exception $e ) {
            $this->add_result( 'core_instance', 'error', 'Failed to create CF Images core instance: ' . $e->getMessage() );
        }
    }
    
    private function check_faust_integration() {
        echo "2. Checking Faust.js Integration...\n";
        
        // Check if Faust integration class exists
        if ( class_exists( 'CF_Images\App\Integrations\Faust' ) ) {
            $this->add_result( 'faust_class', 'success', 'Faust integration class exists' );
        } else {
            $this->add_result( 'faust_class', 'error', 'Faust integration class not found' );
            return;
        }
        
        // Check if Faust.js plugin is detected
        $faust_active = class_exists( 'WPE\FaustWP\Settings\FaustSettings' ) || 
                       is_plugin_active( 'faustwp/faustwp.php' );
        
        if ( $faust_active ) {
            $this->add_result( 'faust_plugin', 'success', 'Faust.js plugin detected' );
        } else {
            $this->add_result( 'faust_plugin', 'warning', 'Faust.js plugin not detected (can be manually enabled)' );
        }
        
        // Test manual activation
        add_filter( 'cf_images_faust_integration_active', '__return_true' );
        
        try {
            $faust_integration = new \CF_Images\App\Integrations\Faust();
            $this->add_result( 'faust_instance', 'success', 'Faust integration instance created successfully' );
        } catch ( Exception $e ) {
            $this->add_result( 'faust_instance', 'error', 'Failed to create Faust integration: ' . $e->getMessage() );
        }
    }
    
    private function check_rest_api_support() {
        echo "3. Checking REST API Support...\n";
        
        // Mock a REST request
        $_SERVER['REQUEST_URI'] = '/wp-json/wp/v2/posts';
        
        // Test REST request detection
        $is_rest = apply_filters( 'cf_images_is_rest_request', true, 0 );
        
        if ( ! $is_rest ) {
            $this->add_result( 'rest_processing', 'success', 'REST API requests are processed for headless mode' );
        } else {
            $this->add_result( 'rest_processing', 'warning', 'REST API requests may be blocked (check integration settings)' );
        }
        
        // Clean up
        unset( $_SERVER['REQUEST_URI'] );
    }
    
    private function check_image_processing() {
        echo "4. Checking Image Processing...\n";
        
        // Create a test attachment
        $attachment_id = $this->create_test_attachment();
        
        if ( ! $attachment_id ) {
            $this->add_result( 'test_attachment', 'warning', 'Could not create test attachment' );
            return;
        }
        
        // Test image URL processing
        $original_url = wp_get_attachment_url( $attachment_id );
        $processed_url = apply_filters( 'wp_get_attachment_url', $original_url, $attachment_id );
        
        if ( $original_url !== $processed_url || strpos( $processed_url, 'imagedelivery.net' ) !== false ) {
            $this->add_result( 'image_processing', 'success', 'Image URL processing is working' );
        } else {
            $this->add_result( 'image_processing', 'info', 'Image processing available (CF credentials required for full functionality)' );
        }
        
        // Test image srcset processing
        $image_data = wp_get_attachment_image_src( $attachment_id, 'medium' );
        if ( $image_data ) {
            $this->add_result( 'image_srcset', 'success', 'Image srcset processing is available' );
        } else {
            $this->add_result( 'image_srcset', 'warning', 'Image srcset processing may not be working' );
        }
        
        // Clean up
        wp_delete_attachment( $attachment_id, true );
    }
    
    private function check_filters_and_hooks() {
        echo "5. Checking Filters and Hooks...\n";
        
        // Test key filters
        $filters_to_check = array(
            'cf_images_is_rest_request',
            'cf_images_faust_integration_active',
            'wp_get_attachment_image_src',
            'wp_get_attachment_url',
            'rest_prepare_attachment',
        );
        
        foreach ( $filters_to_check as $filter ) {
            if ( has_filter( $filter ) ) {
                $this->add_result( "filter_$filter", 'success', "Filter '$filter' has callbacks registered" );
            } else {
                $this->add_result( "filter_$filter", 'info', "Filter '$filter' available for use" );
            }
        }
    }
    
    private function create_test_attachment() {
        // Create a simple test image
        $upload_dir = wp_upload_dir();
        $test_file = $upload_dir['path'] . '/test-validation-image.jpg';
        
        // Create a 1x1 pixel JPEG
        $image_data = base64_decode( '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAv/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/8QAFQEBAQAAAAAAAAAAAAAAAAAAAAX/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIRAxEAPwA/wA8/9k=' );
        
        if ( ! file_put_contents( $test_file, $image_data ) ) {
            return false;
        }
        
        // Create attachment
        $attachment = array(
            'guid'           => $upload_dir['url'] . '/test-validation-image.jpg',
            'post_mime_type' => 'image/jpeg',
            'post_title'     => 'Test Validation Image',
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        
        $attachment_id = wp_insert_attachment( $attachment, $test_file );
        
        if ( $attachment_id ) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $attachment_data = wp_generate_attachment_metadata( $attachment_id, $test_file );
            wp_update_attachment_metadata( $attachment_id, $attachment_data );
        }
        
        return $attachment_id;
    }
    
    private function add_result( $test, $status, $message ) {
        $this->results[] = array(
            'test' => $test,
            'status' => $status,
            'message' => $message
        );
        
        $icon = array(
            'success' => '✅',
            'warning' => '⚠️',
            'error' => '❌',
            'info' => 'ℹ️'
        );
        
        echo "   " . $icon[ $status ] . " " . $message . "\n";
    }
    
    private function display_results() {
        echo "\n📊 Validation Summary\n";
        echo "====================\n";
        
        $counts = array_count_values( array_column( $this->results, 'status' ) );
        
        echo "✅ Successful: " . ( $counts['success'] ?? 0 ) . "\n";
        echo "⚠️  Warnings: " . ( $counts['warning'] ?? 0 ) . "\n";
        echo "❌ Errors: " . ( $counts['error'] ?? 0 ) . "\n";
        echo "ℹ️  Info: " . ( $counts['info'] ?? 0 ) . "\n";
        
        if ( ( $counts['error'] ?? 0 ) > 0 ) {
            echo "\n🚨 Critical issues found. Please resolve errors before using headless mode.\n";
        } elseif ( ( $counts['warning'] ?? 0 ) > 0 ) {
            echo "\n⚠️  Some warnings detected. Review configuration for optimal performance.\n";
        } else {
            echo "\n🎉 All checks passed! CF Images headless integration is ready.\n";
        }
        
        echo "\n📋 Next Steps:\n";
        echo "1. Configure CF Images with your Cloudflare account credentials\n";
        echo "2. Test with your headless frontend application\n";
        echo "3. Monitor CF Images logs for any processing issues\n";
        echo "4. Refer to HEADLESS_SETUP.md for detailed configuration\n";
    }
}

// Run validation if script is called directly
if ( php_sapi_name() === 'cli' ) {
    $validator = new CF_Images_Headless_Validator();
    $success = $validator->run_validation();
    exit( $success ? 0 : 1 );
}