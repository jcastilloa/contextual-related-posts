<?php
/**
 * Contextual Related Posts.
 *
 * Contextual Related Posts is the best related posts plugin for WordPress that
 * allows you to display a list of related posts on your website and in your feed.
 *
 * @package   Contextual_Related_Posts
 * @author    Ajay D'Souza <me@ajaydsouza.com>
 * @license   GPL-2.0+
 * @link      https://webberzone.com
 * @copyright 2009-2015 Ajay D'Souza
 *
 * @wordpress-plugin
 * Plugin Name:	Contextual Related Posts
 * Plugin URI:	https://webberzone.com/plugins/contextual-related-posts/
 * Description:	Display a set of related posts on your website or in your feed. Increase reader retention and reduce bounce rates
 * Version: 	2.2-beta20150831
 * Author: 		WebberZone
 * Author URI: 	https://webberzone.com
 * Text Domain:	crp
 * License: 	GPL-2.0+
 * License URI:	http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:	/languages
 * GitHub Plugin URI: https://github.com/WebberZone/contextual-related-posts/
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * Holds the text domain.
 *
 * @since	1.4
 */
define( 'CRP_LOCAL_NAME', 'crp' );

/**
 * Holds the filesystem directory path (with trailing slash) for CRP
 *
 * @since	1.2
 *
 * @var string
 */
$crp_path = plugin_dir_path( __FILE__ );

/**
 * Holds the URL for CRP
 *
 * @since	1.2
 *
 * @var string
 */
$crp_url = plugins_url() . '/' . plugin_basename( dirname( __FILE__ ) );

/**
 * Global variable holding the current settings for Contextual Related Posts
 *
 * @since	1.8.10
 *
 * @var array
 */
global $crp_settings;
$crp_settings = crp_read_options();


/**
 * Initialises text domain for l10n.
 *
 * @since	2.2.0
 */
function crp_lang_init() {
	load_plugin_textdomain( CRP_LOCAL_NAME, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
}
add_action( 'plugins_loaded', 'crp_lang_init' );


/**
 * Main function to generate the related posts output
 *
 * @since 1.0.1
 *
 * @param	array	$args	Parameters in a query string format
 * @return	string			HTML formatted list of related posts
 */
function get_crp( $args = array() ) {
	global $wpdb, $post, $crp_settings;

	$defaults = array(
		'is_widget' => FALSE,
		'echo' => TRUE,
		'heading' => TRUE,
	);
	$defaults = array_merge( $defaults, $crp_settings );

	// Parse incomming $args into an array and merge it with $defaults
	$args = wp_parse_args( $args, $defaults );

	//Support caching to speed up retrieval
	if ( ! empty( $args['cache'] ) ) {
		$meta_key = 'crp_related_posts';
		if ( $is_widget ) {
			$meta_key .= '_widget';
		}
		if ( is_feed() ) {
			$meta_key .= '_feed';
		}
		$output = get_post_meta( $post->ID, $meta_key, true );
		if ( $output ) {
			return $output;
		}
	}

	//TODO implementar categorías a excluir
	//$exclude_categories = explode( ',', $args['exclude_categories'] );

    $results = get_elastic_crp_posts( array_merge( $args, array(
        'postid' => $post->ID,
        'strict_limit' => TRUE,
    ) ) );

	$output = ( is_singular() ) ? '<div id="crp_related" class="crp_related' . ( $args['is_widget'] ? '_widget' : '' ) . '">' : '<div class="crp_related' . ( $args['is_widget'] ? '_widget' : '' ) . '">';

	if ( is_array($results)) {
		$loop_counter = 0;

		$output .= crp_heading_title( $args );

		$output .= crp_before_list( $args );

		foreach ( $results as $el_result ) {

			/**
			 * Filter the post ID for each result. Allows a custom function to hook in and change the ID if needed.
			 *
			 * @since	1.9
			 *
			 * @param	int	$result->ID	ID of the post
			 */
			$resultid = apply_filters( 'crp_post_id', $el_result['_source']['post_id'] );

			$result = get_post( $resultid );	// Let's get the Post using the ID

			$output .= crp_before_list_item( $args, $result );

			$output .= crp_list_link( $args, $result );

			if ( $args['show_author'] ) {
				$output .= crp_author( $args, $result );
			}

			if ( $args['show_date'] ) {
				$output .= '<span class="crp_date"> ' . mysql2date( get_option( 'date_format', 'd/m/y' ), $result->post_date ) . '</span> ';
			}

			if ( $args['show_excerpt'] ) {
				$output .= '<span class="crp_excerpt"> ' . crp_excerpt( $result->ID, $excerpt_length ) . '</span>';
			}

			$loop_counter++;

			$output .= crp_after_list_item( $args, $result );

			if ( $loop_counter == $args['limit'] ) break;	// End loop when related posts limit is reached
		} //end of foreach loop

		if ( $args['show_credit'] ) {

			$output .= crp_before_list_item( $args, $result );

			$output .= sprintf( __( 'Powered by <a href="%s" rel="nofollow">Contextual Related Posts</a>', CRP_LOCAL_NAME ), esc_url( 'https://webberzone.com/plugins/contextual-related-posts/' ) );

			$output .= crp_after_list_item( $args, $result );

		}

		$output .= crp_after_list( $args );

		$clearfix = '<div class="crp_clear"></div>';

		/**
		 * Filter the clearfix div tag. This is included after the closing tag to clear any miscellaneous floating elements;
		 *
		 * @since	2.0.0
		 *
		 * @param	string	$clearfix	Contains: <div style="clear:both"></div>
		 */
		$output .= apply_filters( 'crp_clearfix', $clearfix );

	} else {
		$output .= ( $args['blank_output'] ) ? ' ' : '<p>' . $args['blank_output_text'] . '</p>';
	}

	// Check if the opening list tag is missing in the output, it means all of our results were eliminated cause of the category filter
	if ( false === ( strpos( $output, $args['before_list_item'] ) ) ) {
		$output = '<div id="crp_related">';
		$output .= ( $args['blank_output'] ) ? ' ' : '<p>' . $args['blank_output_text'] . '</p>';
	}

	$output .= '</div>'; // closing div of 'crp_related'


	//Support caching to speed up retrieval
	if ( ! empty( $args['cache'] ) ) {
		update_post_meta( $post->ID, $meta_key, $output, '' );
	}

	/**
	 * Filter the output
	 *
	 * @since	1.9.1
	 *
	 * @param	string	$output	Formatted list of related posts
	 * @param	array	$args	Complete set of arguments
	 */
	return apply_filters( 'get_crp', $output, $args );
}

function get_elastic_crp_posts( $args = array() )
{

    global $post;

    // Are we matching only the title or the post content as well?
    $match_fields = array(
        'post_title',
    );

    $match_fields_content = array(
        $post->post_title,
    );

    if ($args['match_content']) {

        $match_fields[] = 'post_content';
        $match_fields_content[] = crp_excerpt($post->ID, $args['match_content_words'], false);
    }

    // Limit the related posts by time
    $current_time = current_time('timestamp', 0);
    $from_date = $current_time - ($args['daily_range'] * DAY_IN_SECONDS);
    $from_date = gmdate('Y-m-d H:i:s', $from_date);

    $limit = ( $args['strict_limit'] ) ? $args['limit'] : ( $args['limit'] * 3 );


	$client = \Grupoadslzone\Singletons\ElasticSearchClient::i();

    /*$clientBuilder = Elasticsearch\ClientBuilder::create();
    $clientBuilder->setHosts(array('http://127.0.0.1:9200'));
    $client = $clientBuilder->build();*/


	require_once plugin_dir_path( __FILE__ ) . 'includes/SearchQuery.php';

	$search_query = new SearchQuery();
	$search_query->setParamsByWPPost($post);

	$params = [
		'index' => ep_get_index_name(),
		'type' => 'post',
		'body' =>  [
			'_source' => 'post_id',
			'query' => [
				'more_like_this' => [
					"fields" => $search_query->getFields(),
					"like_text" => $search_query->getSearchQuery(),
					"min_term_freq" => $search_query->getMinTermFreq(),
					"max_query_terms" => $search_query->getMaxQueryTerms(),
					"min_word_length" => 2,
					"minimum_should_match" => $search_query->getMinimumShouldMatch(),
					"boost" => 9,
					"boost_terms" => 4
				],
			],
			'filter' => [
				"bool" => [
					"should" => [
						'range' => [
							'post_date' => [
								'from' => $from_date
							]
						]
					],
					'must_not' => [
							'term' => ["post.post_id" => $post->ID]
					]

				]
			],
			'from' => 0,
			'size' => $limit
		]

	];

	//$ret = json_encode($params['body']);

    $response = $client->search($params);

    $results = $response['hits']['hits'];

    return $results;
}

/**
 * @param $terms
 * @return array
 */
function get_terms_name($terms)
{
	$tags = array();
	foreach ($terms as $term) {
		$tags[] = $term->name;
	}
	return $tags;
}

/**
 * Content function with user defined filter.
 *
 * @since 1.9
 *
 */
function crp_content_prepare_filter() {
	global $crp_settings;

    $priority = isset ( $crp_settings['content_filter_priority'] ) ? $crp_settings['content_filter_priority'] : 10;

	add_filter( 'the_content', 'crp_content_filter', $priority );
}
add_action( 'template_redirect', 'crp_content_prepare_filter' );


/**
 * Filter for 'the_content' to add the related posts.
 *
 * @since 1.0.1
 *
 * @param string $content
 * @return string After the filter has been processed
 */
function crp_content_filter( $content ) {

	global $post, $crp_settings;

	// Return if it's not in the loop or in the main query
	if  ( ! in_the_loop() && ! is_main_query() ) {
		return $content;
	}

	// If this post ID is in the DO NOT DISPLAY list
	$exclude_on_post_ids = explode( ',', $crp_settings['exclude_on_post_ids'] );
	if ( in_array( $post->ID, $exclude_on_post_ids ) ) return $content;	// Exit without adding related posts

	// If this post type is in the DO NOT DISPLAY list
	parse_str( $crp_settings['exclude_on_post_types'], $exclude_on_post_types );	// Save post types in $exclude_on_post_types variable
	if ( in_array( $post->post_type, $exclude_on_post_types ) ) return $content;	// Exit without adding related posts

	// If the DO NOT DISPLAY meta field is set
	$crp_post_meta = get_post_meta( $post->ID, 'crp_post_meta', true );

	if ( isset( $crp_post_meta['crp_disable_here'] ) ) {
		$crp_disable_here = $crp_post_meta['crp_disable_here'];
	} else {
		$crp_disable_here = 0;
	}

	if ( $crp_disable_here ) return $content;

	// Else add the content
    if ( ( is_single() ) && ( $crp_settings['add_to_content'] ) ) {
        return $content.get_crp( 'is_widget=0' );
    } elseif ( ( is_page() ) && ( $crp_settings['add_to_page'] ) ) {
        return $content.get_crp( 'is_widget=0' );
    } elseif ( ( is_home() ) && ( $crp_settings['add_to_home'] ) ) {
        return $content.get_crp( 'is_widget=0' );
    } elseif ( ( is_category() ) && ( $crp_settings['add_to_category_archives'] ) ) {
        return $content.get_crp( 'is_widget=0' );
    } elseif ( ( is_tag() ) && ( $crp_settings['add_to_tag_archives'] ) ) {
        return $content.get_crp( 'is_widget=0' );
    } elseif ( ( ( is_tax() ) || ( is_author() ) || ( is_date() ) ) && ( $crp_settings['add_to_archives'] ) ) {
        return $content.get_crp( 'is_widget=0' );
    } else {
        return $content;
    }
}


/**
 * Filter to add related posts to feeds.
 *
 * @since 1.8.4
 *
 * @param	string	$content
 * @return	string	Formatted content
 */
function crp_rss_filter( $content ) {
	global $post, $crp_settings;

	$limit_feed = $crp_settings['limit_feed'];
	$show_excerpt_feed = $crp_settings['show_excerpt_feed'];
	$post_thumb_op_feed = $crp_settings['post_thumb_op_feed'];

	if ( $crp_settings['add_to_feed'] ) {
		$output = $content;
		$output .= get_crp( 'is_widget=0&limit='.$limit_feed.'&show_excerpt='.$show_excerpt_feed.'&post_thumb_op='.$post_thumb_op_feed );
		return $output;
	} else {
        return $content;
    }
}
add_filter( 'the_excerpt_rss', 'crp_rss_filter' );
add_filter( 'the_content_feed', 'crp_rss_filter' );


/**
 * Echos the related posts. Used for manual install
 *
 * @since 1.0.1
 *
 * @param	string	List of arguments to control the output
 */
function echo_crp( $args = array() ) {
	echo get_crp( $args );
}


/**
 * Enqueue styles.
 *
 * @since 1.9
 *
 */
function crp_heading_styles() {
	global $crp_settings;

	if ( 'rounded_thumbs' == $crp_settings['crp_styles'] ) {
		wp_register_style( 'crp-style-rounded-thumbs', plugins_url( 'css/default-style.css', __FILE__ ) );
		wp_enqueue_style( 'crp-style-rounded-thumbs' );


        $custom_css = "
.crp_related a {
  width: {$crp_settings['thumb_width']}px;
  height: {$crp_settings['thumb_height']}px;
  text-decoration: none;
}
.crp_related img {
  max-width: {$crp_settings['thumb_width']}px;
  margin: auto;
}
                ";

		wp_add_inline_style( 'crp-style-rounded-thumbs', $custom_css );

	}
}
add_action( 'wp_enqueue_scripts', 'crp_heading_styles' );


/**
 * Default options.
 *
 * @since 1.0.1
 *
 * @return array Default options
 */
function crp_default_options() {

	$title = __( '<h3>Related Posts:</h3>', CRP_LOCAL_NAME );

	$blank_output_text = __( 'No related posts found', CRP_LOCAL_NAME );

	$thumb_default = plugins_url( 'default.png' , __FILE__ );

	$crp_get_all_image_sizes = crp_get_all_image_sizes();

	// get relevant post types
	$args = array(
		'public' => true,
		'_builtin' => true
	);
	$post_types = array(
		'post' => 'post',
		'page' => 'page',
	);
	$post_types	= http_build_query( $post_types, '', '&' );

	$crp_settings = array(
		// General options
		'cache' => false,			// Cache output for faster page load

		'add_to_content' => true,		// Add related posts to content (only on single posts)
		'add_to_page' => true,		// Add related posts to content (only on single pages)
		'add_to_feed' => false,		// Add related posts to feed (full)
		'add_to_home' => false,		// Add related posts to home page
		'add_to_category_archives' => false,		// Add related posts to category archives
		'add_to_tag_archives' => false,		// Add related posts to tag archives
		'add_to_archives' => false,		// Add related posts to other archives

		'content_filter_priority' => 10,	// Content priority
		'show_metabox'	=> true,	// Show metabox to admins
		'show_metabox_admins'	=>	false,	// Limit to admins as well

		'show_credit' => false,		// Link to this plugin's page?

		// List tuning options
		'limit' => '6',				// How many posts to display?
		'daily_range' => '1095',				// How old posts should be displayed?

		'match_content' => true,		// Match against post content as well as title
		'match_content_words' => '0',	// How many characters of content should be matched? 0 for all chars

		'post_types' => $post_types,		// WordPress custom post types

		'exclude_categories' => '',	// Exclude these categories
		'exclude_cat_slugs' => '',	// Exclude these categories (slugs)
		'exclude_post_ids' => '',	// Comma separated list of page / post IDs that are to be excluded in the results

		// Output options
		'title' => $title,			// Add before the content
		'blank_output' => true,		// Blank output?
		'blank_output_text' => $blank_output_text,		// Blank output text

		'show_excerpt' => false,			// Show post excerpt in list item
		'show_date' => false,			// Show date in list item
		'show_author' => false,			// Show author in list item
		'excerpt_length' => '10',		// Length of characters
		'title_length' => '60',		// Limit length of post title

		'link_new_window' => false,			// Open link in new window - Includes target="_blank" to links
		'link_nofollow' => false,			// Includes rel="nofollow" to links

		'before_list' => '<ul>',	// Before the entire list
		'after_list' => '</ul>',	// After the entire list
		'before_list_item' => '<li>',	// Before each list item
		'after_list_item' => '</li>',	// After each list item

		'exclude_on_post_ids' => '', 	// Comma separate list of page/post IDs to not display related posts on
		'exclude_on_post_types' => '',		// WordPress custom post types

		// Thumbnail options
		'post_thumb_op' => 'inline',	// Default option to display text and no thumbnails in posts
		'thumb_size' => 'crp_thumbnail',	// Default thumbnail size
		'thumb_height' => '150',	// Height of thumbnails
		'thumb_width' => '150',	// Width of thumbnails
		'thumb_crop' => true,		// Crop mode. default is hard crop
		'thumb_html' => 'html',		// Use HTML or CSS for width and height of the thumbnail?
		'thumb_meta' => 'post-image',	// Meta field that is used to store the location of default thumbnail image
		'scan_images' => true,			// Scan post for images
		'thumb_default' => $thumb_default,	// Default thumbnail image
		'thumb_default_show' => true,	// Show default thumb if none found (if false, don't show thumb at all)

		// Feed options
		'limit_feed' => '5',				// How many posts to display in feeds
		'post_thumb_op_feed' => 'text_only',	// Default option to display text and no thumbnails in Feeds
		'thumb_height_feed' => '50',	// Height of thumbnails in feed
		'thumb_width_feed' => '50',	// Width of thumbnails in feed
		'show_excerpt_feed' => false,			// Show description in list item in feed

		// Custom styles
		'custom_CSS' => '',			// Custom CSS to style the output
		'include_default_style' => true,	// Include default style - Will be DEPRECATED in the next version
		'crp_styles'	=> 'rounded_thumbs'	// Defaault style is rounded thubnails
	);


	/**
	 * Filters the default options array.
	 *
	 * @since	1.9.1
	 *
	 * @param	array	$crp_settings	Default options
	 */
	return apply_filters( 'crp_default_options', $crp_settings );
}


/**
 * Function to read options from the database.
 *
 * @since 1.0.1
 *
 * @return array Contextual Related Posts options
 */
function crp_read_options() {
	$crp_settings_changed = false;

	$defaults = crp_default_options();

	$crp_settings = array_map( 'stripslashes', (array) get_option( 'ald_crp_settings') );
	unset( $crp_settings[0] ); // produced by the (array) casting when there's nothing in the DB

	foreach ( $defaults as $k=>$v ) {
		if ( ! isset( $crp_settings[ $k ] ) ) {
			$crp_settings[ $k ] = $v;
		}
		$crp_settings_changed = true;
	}
	if ( true == $crp_settings_changed ) {
		update_option('ald_crp_settings', $crp_settings);
	}

	/**
	 * Filters the options array.
	 *
	 * @since	1.9.1
	 *
	 * @param	array	$crp_settings	Options read from the database
	 */
	return apply_filters( 'crp_read_options', $crp_settings );
}


/**
 * Filter for wp_head to include the custom CSS.
 *
 * @since 1.8.4
 *
 * @return	string	Echoed string with the CSS output in the Header
 */
function crp_header() {
	global $wpdb, $post, $crp_settings;

	$crp_custom_CSS = stripslashes( $crp_settings['custom_CSS'] );

	// Add CSS to header
	if ( '' != $crp_custom_CSS ) {
	    if ( ( is_single() ) ) {
			echo '<style type="text/css">'.$crp_custom_CSS.'</style>';
	    } elseif((is_page())) {
			echo '<style type="text/css">'.$crp_custom_CSS.'</style>';
	    } elseif ( ( is_home() ) && ( $crp_settings['add_to_home'] ) ) {
			echo '<style type="text/css">'.$crp_custom_CSS.'</style>';
	    } elseif ( ( is_category() ) && ( $crp_settings['add_to_category_archives'] ) ) {
			echo '<style type="text/css">'.$crp_custom_CSS.'</style>';
	    } elseif ( ( is_tag() ) && ( $crp_settings['add_to_tag_archives'] ) ) {
			echo '<style type="text/css">'.$crp_custom_CSS.'</style>';
	    } elseif( ( ( is_tax() ) || ( is_author() ) || ( is_date() ) ) && ( $crp_settings['add_to_archives'] ) ) {
			echo '<style type="text/css">'.$crp_custom_CSS.'</style>';
	    } elseif ( is_active_widget( false, false, 'CRP_Widget', true ) ) {
			echo '<style type="text/css">'.$crp_custom_CSS.'</style>';
	    }
	}
}
add_action( 'wp_head', 'crp_header' );


/**
 * Function to create an excerpt for the post.
 *
 * @since 1.6
 *
 * @param int $id Post ID
 * @param int|string $excerpt_length Length of the excerpt in words
 * @return string Excerpt
 */
function crp_excerpt( $id, $excerpt_length = 0, $use_excerpt = true ) {
	$content = $excerpt = '';

	if ( $use_excerpt ) {
		$content = get_post( $id )->post_excerpt;
	}
	if ( '' == $content ) {
		$content = get_post( $id )->post_content;
	}

	$output = strip_tags( strip_shortcodes( $content ) );

	if ( $excerpt_length > 0 ) {
		$output = wp_trim_words( $output, $excerpt_length );
	}

	/**
	 * Filters excerpt generated by CRP.
	 *
	 * @since	1.9
	 *
	 * @param	array	$output			Formatted excerpt
	 * @param	int		$id				Post ID
	 * @param	int		$excerpt_length	Length of the excerpt
	 * @param	boolean	$use_excerpt	Use the excerpt?
	 */
	return apply_filters( 'crp_excerpt', $output, $id, $excerpt_length, $use_excerpt );
}


/**
 * Function to limit content by characters.
 *
 * @since 1.8.4
 *
 * @param	string 	$content 	Content to be used to make an excerpt
 * @param	int 	$no_of_char	Maximum length of excerpt in characters
 * @return 	string				Formatted content
 */
function crp_max_formatted_content( $content, $no_of_char = -1 ) {
	$content = strip_tags( $content );  // Remove CRLFs, leaving space in their wake

	if ( ( $no_of_char > 0 ) && ( strlen( $content ) > $no_of_char ) ) {
		$aWords = preg_split( "/[\s]+/", substr( $content, 0, $no_of_char ) );

		// Break back down into a string of words, but drop the last one if it's chopped off
		if ( substr( $content, $no_of_char, 1 ) == " " ) {
		  $content = implode( " ", $aWords );
		} else {
		  $content = implode( " ", array_slice( $aWords, 0, -1 ) ) .'&hellip;';
		}
	}

	/**
	 * Filters formatted content after cropping.
	 *
	 * @since	1.9
	 *
	 * @param	string	$content	Formatted content
	 * @param	int		$no_of_char	Maximum length of excerpt in characters
	 */
	return apply_filters( 'crp_max_formatted_content' , $content, $no_of_char );
}


/**
 * Get all image sizes.
 *
 * @since	2.0.0
 * @param	string	$size	Get specific image size
 * @return	array	Image size names along with width, height and crop setting
 */
function crp_get_all_image_sizes( $size = '' ) {
	global $_wp_additional_image_sizes;

	/* Get the intermediate image sizes and add the full size to the array. */
	$intermediate_image_sizes = get_intermediate_image_sizes();

	foreach( $intermediate_image_sizes as $_size ) {
        if ( in_array( $_size, array( 'thumbnail', 'medium', 'large' ) ) ) {

            $sizes[ $_size ]['name'] = $_size;
            $sizes[ $_size ]['width'] = get_option( $_size . '_size_w' );
            $sizes[ $_size ]['height'] = get_option( $_size . '_size_h' );
            $sizes[ $_size ]['crop'] = (bool) get_option( $_size . '_crop' );

	        if ( ( 0 == $sizes[ $_size ]['width'] ) && ( 0 == $sizes[ $_size ]['height'] ) ) {
	            unset( $sizes[ $_size ] );
	        }

        } elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {

            $sizes[ $_size ] = array(
	            'name' => $_size,
                'width' => $_wp_additional_image_sizes[ $_size ]['width'],
                'height' => $_wp_additional_image_sizes[ $_size ]['height'],
                'crop' => (bool) $_wp_additional_image_sizes[ $_size ]['crop'],
            );
		}
	}

	/* Get only 1 size if found */
    if ( $size ) {
        if ( isset( $sizes[ $size ] ) ) {
			return $sizes[ $size ];
        } else {
			return false;
        }
    }

	/**
	 * Filters array of image sizes.
	 *
	 * @since	2.0
	 *
	 * @param	array	$sizes	Image sizes
	 */
	return apply_filters( 'crp_get_all_image_sizes', $sizes );
}


/*----------------------------------------------------------------------------*
 * Activate the plugin
 *----------------------------------------------------------------------------*/

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-plugin-name-activator.php
 *
 * @since 2.2.0
 *
 */
function activate_crp( $network_wide ) {
	require_once plugin_dir_path( __FILE__ ) . 'includes/plugin-activator.php';
	crp_activate( $network_wide );
}
register_activation_hook( __FILE__, 'activate_crp' );


/**
 * Fired when a new site is activated with a WPMU environment.
 *
 * @since 2.0.0
 *
 * @param    int    $blog_id    ID of the new blog.
 */
function crp_activate_new_site( $blog_id ) {

	if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
		return;
	}

	require_once plugin_dir_path( __FILE__ ) . 'includes/plugin-activator.php';

	switch_to_blog( $blog_id );
	crp_single_activate();
	restore_current_blog();

}
add_action( 'wpmu_new_blog', 'crp_activate_new_site' );



/*----------------------------------------------------------------------------*
 * WordPress widget
 *----------------------------------------------------------------------------*/

/**
 * Initialise the widget.
 *
 * @since 1.9.1
 *
 */
function register_crp_widget() {
	require_once( plugin_dir_path( __FILE__ ) . 'includes/class-crp-widget.php' );

	register_widget( 'CRP_Widget' );
}
add_action( 'widgets_init', 'register_crp_widget' );


/*----------------------------------------------------------------------------*
 * CRP modules
 *----------------------------------------------------------------------------*/

require_once( plugin_dir_path( __FILE__ ) . 'includes/output-generator.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/media-handler.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/modules/manual-posts.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/modules/shortcode.php' );
require_once( plugin_dir_path( __FILE__ ) . 'includes/modules/taxonomies.php' );


/*----------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 *----------------------------------------------------------------------------*/

if ( is_admin() || strstr( $_SERVER['PHP_SELF'], 'wp-admin/' ) ) {

	require_once( plugin_dir_path( __FILE__ ) . 'admin/admin.php' );
	require_once( plugin_dir_path( __FILE__ ) . 'admin/metabox.php' );

} // End admin.inc


/*----------------------------------------------------------------------------*
 * Deprecated functions
 *----------------------------------------------------------------------------*/

require_once( plugin_dir_path( __FILE__ ) . 'includes/deprecated.php' );


