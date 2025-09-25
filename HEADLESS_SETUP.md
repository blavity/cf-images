# Headless WordPress Support with Faust.js

This document outlines the CF Images plugin's support for headless WordPress setups, particularly those using the Faust.js framework.

## Overview

The CF Images plugin now includes dedicated support for headless WordPress configurations. When WordPress is used as a headless CMS with frontends built using frameworks like Next.js, Nuxt.js, or other JavaScript frameworks, images need to be properly processed and delivered through REST API endpoints and GraphQL queries.

## What is Faust.js?

[Faust.js](https://github.com/wpengine/faustjs) is a framework created by WP Engine for building headless WordPress applications. It provides:

- WordPress authentication and authorization
- URL rewriting and routing
- Data fetching utilities
- Image optimization helpers
- Next.js and Nuxt.js integrations

## Plugin Integration Features

### Automatic Detection

The plugin automatically detects headless setups through:

1. **Faust.js Plugin Detection**: Checks if the `faustwp/faustwp.php` plugin is active
2. **Class Detection**: Looks for Faust.js related classes like `WPE\FaustWP\Settings\FaustSettings`
3. **Manual Activation**: Can be enabled via constant or filter (see Configuration below)

### REST API Support

By default, the CF Images plugin skips image processing during REST API requests to avoid conflicts with admin functionality. However, for headless setups, this behavior can be overridden:

- **Image Replacement in REST Responses**: Processes attachment objects in `/wp-json/wp/v2/media` endpoints
- **Content Image Processing**: Handles images within post/page content delivered via REST API
- **Size Variants**: Ensures all image sizes are properly converted to Cloudflare URLs

### GraphQL Compatibility

When WPGraphQL is active alongside Faust.js:

- **Query Processing**: Images in GraphQL responses are processed
- **Custom Field Support**: Works with ACF and other custom field plugins
- **Extensible**: Provides hooks for custom GraphQL processing

### Advanced Custom Fields (ACF) Integration

Enhanced ACF support for headless environments:

- **Image Field Processing**: Converts ACF image field URLs to Cloudflare URLs
- **Array Format Support**: Handles both array and string formats for ACF image fields
- **Size Variants**: Processes all available image sizes within ACF image arrays
- **REST API Exposure**: Works with ACF's REST API endpoints

## Configuration

### Automatic Configuration

If you have the Faust.js plugin active, no additional configuration is required. The integration will automatically enable.

### Manual Configuration

#### Via WordPress Constant

Add this to your `wp-config.php` file:

```php
define( 'CF_IMAGES_HEADLESS_MODE', true );
```

#### Via Filter

Add this to your theme's `functions.php` or a plugin:

```php
add_filter( 'cf_images_faust_integration_active', '__return_true' );
```

### Integration Options

The Faust.js integration provides several configurable options through the CF Images settings:

1. **Process images in REST API** (Default: Enabled)
   - Enables image processing for REST API requests
   - Essential for headless setups

2. **Process images in GraphQL responses** (Default: Enabled)
   - Enables image processing for GraphQL responses
   - Requires WPGraphQL to be active

3. **Process ACF image fields** (Default: Enabled)
   - Processes image URLs in Advanced Custom Fields
   - Works with both REST API and GraphQL exposure

4. **Process content images** (Default: Enabled)
   - Processes images within post/page content
   - Handles inline images in the content editor

## Frontend Implementation

### Using with Next.js and Faust.js

When using Faust.js with Next.js, images from your WordPress backend will automatically be served through Cloudflare Images:

```javascript
// Example: Using WordPress data in Next.js component
import { getWordPressProps } from '@faustjs/next';

export default function BlogPost({ post }) {
  return (
    <article>
      <h1>{post.title}</h1>
      {/* Featured image will automatically use Cloudflare URL */}
      {post.featuredImage && (
        <img 
          src={post.featuredImage.node.sourceUrl} 
          alt={post.featuredImage.node.altText}
        />
      )}
      {/* Content images will also use Cloudflare URLs */}
      <div dangerouslySetInnerHTML={{ __html: post.content }} />
    </article>
  );
}

export async function getStaticProps(context) {
  return getWordPressProps({ context });
}
```

### Using with Nuxt.js

Similar behavior applies when using Nuxt.js with Faust.js:

```vue
<template>
  <article>
    <h1>{{ post.title }}</h1>
    <img 
      v-if="post.featuredImage" 
      :src="post.featuredImage.node.sourceUrl"
      :alt="post.featuredImage.node.altText"
    />
    <div v-html="post.content"></div>
  </article>
</template>

<script>
export default {
  async asyncData({ $faust, route }) {
    const { post } = await $faust.getPost({ 
      uri: route.path,
      preview: route.query.preview 
    });
    return { post };
  }
}
</script>
```

### Direct REST API Usage

If you're not using Faust.js but building a custom headless frontend:

```javascript
// Fetch posts with processed images
const response = await fetch('https://your-wp-site.com/wp-json/wp/v2/posts');
const posts = await response.json();

posts.forEach(post => {
  // Images in content are already processed
  console.log(post.content.rendered); // Contains Cloudflare URLs
  
  // Featured image URLs are processed
  if (post.featured_media) {
    // Fetch the media object
    fetchMedia(post.featured_media).then(media => {
      console.log(media.source_url); // Cloudflare URL
    });
  }
});
```

## Troubleshooting

### Images Not Being Processed

1. **Verify Integration Status**: Check that the Faust.js integration appears in CF Images settings
2. **REST API Option**: Ensure "Process images in REST API" is enabled
3. **Plugin Detection**: Verify that either Faust.js plugin is active or manual activation is configured

### Performance Considerations

1. **Caching**: Implement proper caching for your REST API requests
2. **Image Sizes**: Register appropriate image sizes for your frontend needs
3. **CDN**: Consider using a CDN for your JSON responses if serving global audiences

### GraphQL Issues

1. **WPGraphQL Required**: GraphQL support requires the WPGraphQL plugin
2. **Custom Fields**: Ensure ACF or other custom field plugins have GraphQL support enabled
3. **Query Depth**: Be mindful of GraphQL query depth limits

## Hooks and Filters

### Available Filters

```php
// Control when the Faust integration is active
add_filter( 'cf_images_faust_integration_active', function( $active ) {
    return $active || is_headless_request();
});

// Process custom GraphQL data
add_filter( 'cf_images_process_graphql_data', function( $data, $integration ) {
    // Custom GraphQL processing logic
    return $data;
}, 10, 2 );

// Override REST request detection for headless
add_filter( 'cf_images_is_rest_request', function( $is_rest, $attachment_id ) {
    // Custom logic to determine if this should be processed
    return $is_rest;
}, 10, 2 );
```

### Available Actions

```php
// Runs when Faust integration initializes
add_action( 'cf_images_faust_init', function() {
    // Your custom initialization code
});
```

## Best Practices

1. **Test Both Environments**: Ensure images work in both traditional WordPress and headless contexts
2. **Image Sizes**: Define appropriate image sizes for your frontend breakpoints
3. **Alt Text**: Always include alt text for accessibility
4. **Loading Strategy**: Implement lazy loading and responsive images in your frontend
5. **Fallbacks**: Have fallback strategies for when images fail to load

## Compatibility

This integration has been tested with:

- WordPress 5.6+
- Faust.js 0.13+
- WPGraphQL 1.6+
- ACF Pro 5.12+
- Next.js 12+
- Nuxt.js 3.0+

## Support

If you encounter issues with headless WordPress support:

1. Check the CF Images plugin logs
2. Verify your REST API endpoints are working
3. Test with the WordPress admin to ensure basic plugin functionality
4. Create a support ticket with specific error messages and configuration details