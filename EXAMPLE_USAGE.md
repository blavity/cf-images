# CF Images + Faust.js Integration Example

This example demonstrates how the CF Images plugin integrates with Faust.js for headless WordPress setups.

## WordPress Backend Setup

### 1. Install Required Plugins
```bash
# Install CF Images plugin
wp plugin install --activate cf-images

# Install Faust.js plugin  
wp plugin install --activate https://github.com/wpengine/faustwp/releases/latest/download/faustwp.zip

# Optional: Install WPGraphQL for GraphQL support
wp plugin install --activate wp-graphql
```

### 2. Configure CF Images
```php
// wp-config.php
define( 'CF_IMAGES_ACCOUNT_ID', 'your-account-id' );
define( 'CF_IMAGES_KEY_TOKEN', 'your-api-token' );

// Optional: Force headless mode even without Faust.js plugin
define( 'CF_IMAGES_HEADLESS_MODE', true );
```

## REST API Response Comparison

### Before CF Images + Headless Integration

**GET /wp-json/wp/v2/media/123**
```json
{
  "id": 123,
  "source_url": "https://yoursite.com/wp-content/uploads/2024/01/image.jpg",
  "media_details": {
    "sizes": {
      "medium": {
        "source_url": "https://yoursite.com/wp-content/uploads/2024/01/image-300x200.jpg",
        "width": 300,
        "height": 200
      },
      "large": {
        "source_url": "https://yoursite.com/wp-content/uploads/2024/01/image-1024x683.jpg", 
        "width": 1024,
        "height": 683
      }
    }
  }
}
```

### After CF Images + Headless Integration

**GET /wp-json/wp/v2/media/123**
```json
{
  "id": 123,
  "source_url": "https://imagedelivery.net/your-hash/your-image-id/public",
  "media_details": {
    "sizes": {
      "medium": {
        "source_url": "https://imagedelivery.net/your-hash/your-image-id/w=300",
        "width": 300,
        "height": 200
      },
      "large": {
        "source_url": "https://imagedelivery.net/your-hash/your-image-id/w=1024",
        "width": 1024, 
        "height": 683
      }
    }
  }
}
```

## Frontend Implementation Examples

### Next.js with Faust.js

```javascript
// pages/posts/[...uri].js
import { getWordPressProps, WordPressTemplate } from '@faustjs/next';

export default function Post(props) {
  const { post } = props;
  
  return (
    <article>
      <h1>{post.title}</h1>
      
      {/* Featured Image - Automatically uses Cloudflare URL */}
      {post.featuredImage && (
        <img 
          src={post.featuredImage.node.sourceUrl}
          alt={post.featuredImage.node.altText}
          width={post.featuredImage.node.mediaDetails.width}
          height={post.featuredImage.node.mediaDetails.height}
        />
      )}
      
      {/* Content with processed images */}
      <div 
        dangerouslySetInnerHTML={{ __html: post.content }} 
      />
    </article>
  );
}

export async function getServerSideProps(context) {
  return getWordPressProps({ context });
}

Post.query = `
  query GetPost($uri: String!) {
    post(id: $uri, idType: URI) {
      title
      content
      featuredImage {
        node {
          sourceUrl
          altText
          mediaDetails {
            width
            height
            sizes {
              name
              sourceUrl
              width
              height
            }
          }
        }
      }
    }
  }
`;
```

### Nuxt.js with Custom REST API Integration

```vue
<!-- pages/blog/_slug.vue -->
<template>
  <article>
    <h1>{{ post.title.rendered }}</h1>
    
    <!-- Featured Image -->
    <img 
      v-if="featuredImage"
      :src="featuredImage.source_url"
      :alt="featuredImage.alt_text"
      :width="featuredImage.media_details.width"
      :height="featuredImage.media_details.height"
    />
    
    <!-- Post Content with processed images -->
    <div v-html="post.content.rendered"></div>
  </article>
</template>

<script>
export default {
  async asyncData({ $axios, params }) {
    // Fetch post data
    const post = await $axios.$get(`/wp-json/wp/v2/posts`, {
      params: { slug: params.slug }
    });
    
    let featuredImage = null;
    if (post[0].featured_media) {
      // Fetch featured image - URLs will be Cloudflare URLs
      featuredImage = await $axios.$get(
        `/wp-json/wp/v2/media/${post[0].featured_media}`
      );
    }
    
    return {
      post: post[0],
      featuredImage
    };
  }
}
</script>
```

### React with Custom Hook

```javascript
// hooks/useWordPressPost.js
import { useState, useEffect } from 'react';

export function useWordPressPost(slug) {
  const [post, setPost] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    async function fetchPost() {
      try {
        const response = await fetch(
          `${process.env.WORDPRESS_URL}/wp-json/wp/v2/posts?slug=${slug}`
        );
        const posts = await response.json();
        
        if (posts.length === 0) {
          setError('Post not found');
          return;
        }
        
        const post = posts[0];
        
        // Fetch featured image if available
        if (post.featured_media) {
          const imageResponse = await fetch(
            `${process.env.WORDPRESS_URL}/wp-json/wp/v2/media/${post.featured_media}`
          );
          post.featuredImage = await imageResponse.json();
        }
        
        setPost(post);
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    }

    fetchPost();
  }, [slug]);

  return { post, loading, error };
}

// components/BlogPost.js
import { useWordPressPost } from '../hooks/useWordPressPost';

export default function BlogPost({ slug }) {
  const { post, loading, error } = useWordPressPost(slug);
  
  if (loading) return <div>Loading...</div>;
  if (error) return <div>Error: {error}</div>;
  if (!post) return <div>Post not found</div>;

  return (
    <article>
      <h1>{post.title.rendered}</h1>
      
      {post.featuredImage && (
        <img
          src={post.featuredImage.source_url} // Cloudflare URL
          alt={post.featuredImage.alt_text}
          width={post.featuredImage.media_details.width}
          height={post.featuredImage.media_details.height}
        />
      )}
      
      <div 
        dangerouslySetInnerHTML={{ __html: post.content.rendered }}
      />
    </article>
  );
}
```

## ACF Integration Example

### WordPress Setup with ACF

```php
// Register ACF fields
if( function_exists('acf_add_local_field_group') ):

acf_add_local_field_group(array(
    'key' => 'group_hero_section',
    'title' => 'Hero Section',
    'fields' => array(
        array(
            'key' => 'field_hero_image',
            'label' => 'Hero Image',
            'name' => 'hero_image',
            'type' => 'image',
            'return_format' => 'array',
            'show_in_rest' => 1, // Enable REST API
        ),
    ),
    'location' => array(
        array(
            array(
                'param' => 'post_type',
                'operator' => '==',
                'value' => 'post',
            ),
        ),
    ),
    'show_in_rest' => 1,
));

endif;
```

### Frontend Usage

```javascript
// Next.js component using ACF data
export default function HeroSection({ post }) {
  const heroImage = post.acf.hero_image;
  
  if (!heroImage) return null;
  
  return (
    <section className="hero">
      {/* ACF image URL is automatically processed through Cloudflare */}
      <img
        src={heroImage.url}
        alt={heroImage.alt}
        width={heroImage.width}
        height={heroImage.height}
      />
      
      {/* Different sizes are also processed */}
      <picture>
        <source 
          media="(max-width: 768px)" 
          srcSet={heroImage.sizes.medium}
        />
        <source 
          media="(max-width: 1024px)" 
          srcSet={heroImage.sizes.large}
        />
        <img 
          src={heroImage.sizes.full} 
          alt={heroImage.alt}
        />
      </picture>
    </section>
  );
}
```

## Performance Optimization

### Image Lazy Loading

```javascript
// components/OptimizedImage.js
import { useState, useRef, useEffect } from 'react';

export default function OptimizedImage({ 
  src, 
  alt, 
  width, 
  height, 
  className = '',
  ...props 
}) {
  const [isLoaded, setIsLoaded] = useState(false);
  const [isInView, setIsInView] = useState(false);
  const imgRef = useRef();

  useEffect(() => {
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setIsInView(true);
          observer.disconnect();
        }
      },
      { threshold: 0.1 }
    );

    if (imgRef.current) {
      observer.observe(imgRef.current);
    }

    return () => observer.disconnect();
  }, []);

  return (
    <div 
      ref={imgRef}
      className={`image-container ${className}`}
      style={{ width, height }}
    >
      {isInView && (
        <img
          src={src}
          alt={alt}
          width={width}
          height={height}
          onLoad={() => setIsLoaded(true)}
          className={isLoaded ? 'loaded' : 'loading'}
          {...props}
        />
      )}
    </div>
  );
}
```

### Responsive Images with Cloudflare Transformations

```javascript
// utils/imageUtils.js
export function generateResponsiveImageProps(cfImageUrl, alt, originalWidth, originalHeight) {
  const breakpoints = [320, 640, 768, 1024, 1280, 1920];
  
  const srcSet = breakpoints
    .filter(width => width <= originalWidth)
    .map(width => {
      const height = Math.round((originalHeight / originalWidth) * width);
      return `${cfImageUrl}/w=${width},h=${height} ${width}w`;
    })
    .join(', ');

  return {
    src: `${cfImageUrl}/w=${originalWidth}`,
    srcSet,
    sizes: '(max-width: 768px) 100vw, (max-width: 1024px) 50vw, 33vw',
    alt,
    width: originalWidth,
    height: originalHeight,
  };
}

// Usage in component
import { generateResponsiveImageProps } from '../utils/imageUtils';

export default function ResponsiveImage({ image }) {
  const imageProps = generateResponsiveImageProps(
    image.source_url, // This is already a Cloudflare URL
    image.alt_text,
    image.media_details.width,
    image.media_details.height
  );

  return <img {...imageProps} />;
}
```

## Debugging and Troubleshooting

### Verify Integration is Working

```javascript
// Add to your frontend to verify Cloudflare URLs
function checkCloudflareIntegration() {
  fetch('/wp-json/wp/v2/media/123') // Replace with actual media ID
    .then(response => response.json())
    .then(media => {
      const isCloudflare = media.source_url.includes('imagedelivery.net');
      console.log('Cloudflare integration:', isCloudflare ? '✅ Working' : '❌ Not working');
      console.log('Image URL:', media.source_url);
    });
}
```

### WordPress Debug Information

```php
// Add to wp-config.php for debugging
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// Add to functions.php to log CF Images processing
add_action('wp_ajax_cf_images_debug', function() {
    $attachment_id = intval($_POST['attachment_id']);
    $debug_info = array(
        'faust_active' => class_exists('WPE\FaustWP\Settings\FaustSettings'),
        'cf_images_active' => class_exists('CF_Images\App\Core'),
        'rest_api_enabled' => apply_filters('cf_images_integration_option_value', true, 'rest_api_support'),
        'attachment_processed' => get_post_meta($attachment_id, '_cloudflare_image_id', true),
    );
    wp_send_json_success($debug_info);
});
```

This example shows how the CF Images plugin seamlessly integrates with headless WordPress setups, providing optimized image delivery through Cloudflare while maintaining full compatibility with modern frontend frameworks.