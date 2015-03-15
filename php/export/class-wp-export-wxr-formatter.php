<?php
/**
 * Version number for the export format.
 *
 * Bump this when something changes that might affect compatibility.
 *
 * @since 2.5.0
 */
define( 'WXR_VERSION', '1.2' );

/**
 * Responsible for formatting the data in WP_Export_Query to WXR
 */
class WP_Export_WXR_Formatter {
	public function __construct( $export ) {
		$this->export = $export;
		$this->wxr_version = WXR_VERSION;
	}

	public function before_posts() {
		$before_posts_xml = '';
		$before_posts_xml .= $this->header();
		$before_posts_xml .= $this->site_metadata();
		$before_posts_xml .= $this->authors();
		$before_posts_xml .= $this->categories();
		$before_posts_xml .= $this->tags();
		$before_posts_xml .= $this->nav_menu_terms();
		$before_posts_xml .= $this->custom_taxonomies_terms();
		$before_posts_xml .= $this->rss2_head_action();
		return $before_posts_xml;
	}

	public function posts() {
		return new WP_Map_Iterator( $this->export->posts(), array( $this, 'post' ) );
	}

	public function after_posts() {
		return $this->footer();
	}

	public function header() {
		$oxymel = new Oxymel;
		$charset = $this->export->charset();
		$wp_generator_tag = $this->export->wp_generator_tag();
		$comment = <<<COMMENT

 This is a WordPress eXtended RSS file generated by WordPress as an export of your site.
 It contains information about your site's posts, pages, comments, categories, and other content.
 You may use this file to transfer that content from one site to another.
 This file is not intended to serve as a complete backup of your site.

 To import this information into a WordPress site follow these steps:
 1. Log in to that site as an administrator.
 2. Go to Tools: Import in the WordPress admin panel.
 3. Install the "WordPress" importer from the list.
 4. Activate & Run Importer.
 5. Upload this file using the form provided on that page.
 6. You will first be asked to map the authors in this export file to users
    on the site. For each author, you may choose to map to an
    existing user on the site or to create a new user.
 7. WordPress will then import each of the posts, pages, comments, categories, etc.
    contained in this file into your site.

COMMENT;
		return $oxymel
			->xml
			->comment( $comment )
			->raw( $wp_generator_tag )
			->open_rss( array(
				'version' => '2.0',
				'xmlns:excerpt' => "http://wordpress.org/export/{$this->wxr_version}/excerpt/",
				'xmlns:content' => "http://purl.org/rss/1.0/modules/content/",
				'xmlns:wfw' => "http://wellformedweb.org/CommentAPI/",
				'xmlns:dc' => "http://purl.org/dc/elements/1.1/",
				'xmlns:wp' => "http://wordpress.org/export/{$this->wxr_version}/",
			) )
				->open_channel
				->to_string();

	}

	public function site_metadata() {
		$oxymel = new Oxymel;
		$metadata = $this->export->site_metadata();
		return $oxymel
			->title( $metadata['name'] )
			->link( $metadata['url'] )
			->description( $metadata['description'] )
			->pubDate( $metadata['pubDate'] )
			->language( $metadata['language'] )
			->tag( 'wp:wxr_version', $this->wxr_version )
			->tag( 'wp:base_site_url', $metadata['site_url'] )
			->tag( 'wp:base_blog_url', $metadata['blog_url'] )
			->to_string();
	}

	public function authors() {
		$oxymel = new Oxymel;
		$authors = $this->export->authors();
		foreach ( $authors as $author ) {
			$oxymel
				->tag( 'wp:wp_author' )->contains
					->tag( 'wp:author_id', $author->ID )
					->tag( 'wp:author_login', $author->user_login )
					->tag( 'wp:author_email', $author->user_email )
					->tag( 'wp:author_display_name' )->contains->cdata( $author->display_name )->end
					->tag( 'wp:author_first_name' )->contains->cdata( $author->user_firstname )->end
					->tag( 'wp:author_last_name' )->contains->cdata( $author->user_lastname )->end
					->end;
		}
		return $oxymel->to_string();
	}

	public function categories() {
		$oxymel = new WP_Export_Oxymel;
		$categories = $this->export->categories();
		foreach( $categories as $term_id => $category ) {
			$category->parent_slug = $category->parent? $categories[$category->parent]->slug : '';
			$oxymel->tag( 'wp:category' )->contains
				->tag( 'wp:term_id', $category->term_id )
				->tag( 'wp:category_nicename', $category->slug )
				->tag( 'wp:category_parent', $category->parent_slug )
				->optional_cdata( 'wp:cat_name', $category->name )
				->optional_cdata( 'wp:category_description', $category->description )
				->end;
		}
		return $oxymel->to_string();
	}

	public function tags() {
		$oxymel = new WP_Export_Oxymel;
		$tags = $this->export->tags();
		foreach( $tags as $tag ) {
			$oxymel->tag( 'wp:tag' )->contains
				->tag( 'wp:term_id', $tag->term_id )
				->tag( 'wp:tag_slug', $tag->slug )
				->optional_cdata( 'wp:tag_name', $tag->name )
				->optional_cdata( 'wp:tag_description', $tag->description )
				->end;
		}
		return $oxymel->to_string();
	}

	public function nav_menu_terms() {
		return $this->terms( $this->export->nav_menu_terms() );
	}

	public function custom_taxonomies_terms() {
		return $this->terms( $this->export->custom_taxonomies_terms() );
	}

	public function rss2_head_action() {
		ob_start();
		do_action( 'rss2_head' );
		$action_output = ob_get_clean();
		return $action_output;
	}

	public function post( $post ) {
		$oxymel = new WP_Export_Oxymel;
		$GLOBALS['wp_query']->in_the_loop = true;
		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$oxymel->item->contains
			->title( apply_filters( 'the_title_rss', $post->post_title ) )
			->link( esc_url( apply_filters('the_permalink_rss', get_permalink() ) ) )
			->pubDate( mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ) )
			->tag( 'dc:creator', get_the_author_meta( 'login' ) )
			->guid( esc_url( get_the_guid() ), array( 'isPermaLink' => 'false' ) )
			->description( '' )
			->tag( 'content:encoded' )->contains->cdata( $post->post_content )->end
			->tag( 'excerpt:encoded' )->contains->cdata( $post->post_excerpt )->end
			->tag( 'wp:post_id', $post->ID )
			->tag( 'wp:post_date', $post->post_date )
			->tag( 'wp:post_date_gmt', $post->post_date_gmt )
			->tag( 'wp:comment_status', $post->comment_status )
			->tag( 'wp:ping_status', $post->ping_status )
			->tag( 'wp:post_name', $post->post_name )
			->tag( 'wp:status', $post->post_status )
			->tag( 'wp:post_parent', $post->post_parent )
			->tag( 'wp:menu_order', $post->menu_order )
			->tag( 'wp:post_type', $post->post_type )
			->tag( 'wp:post_password', $post->post_password )
			->tag( 'wp:is_sticky', $post->is_sticky )
			->optional( 'wp:attachment_url', wp_get_attachment_url( $post->ID ) );
		foreach( $post->terms as $term ) {
			$oxymel
			->category( array( 'domain' => $term->taxonomy, 'nicename' => $term->slug  ) )->contains->cdata( $term->name )->end;
		}
		foreach( $post->meta as $meta ) {
			$oxymel
			->tag( 'wp:postmeta' )->contains
				->tag( 'wp:meta_key', $meta->meta_key )
				->tag( 'wp:meta_value' )->contains->cdata( $meta->meta_value )->end
				->end;
		}
		foreach( $post->comments as $comment ) {
			$oxymel
			->tag( 'wp:comment' )->contains
				->tag( 'wp:comment_id', $comment->comment_ID )
				->tag( 'wp:comment_author' )->contains->cdata( $comment->comment_author )->end
				->tag( 'wp:comment_author_email', $comment->comment_author_email )
				->tag( 'wp:comment_author_url', esc_url( $comment->comment_author_url ) )
				->tag( 'wp:comment_author_IP', $comment->comment_author_IP )
				->tag( 'wp:comment_date', $comment->comment_date )
				->tag( 'wp:comment_date_gmt', $comment->comment_date_gmt )
				->tag( 'wp:comment_content' )->contains->cdata( $comment->comment_content )->end
				->tag( 'wp:comment_approved', $comment->comment_approved )
				->tag( 'wp:comment_type', $comment->comment_type )
				->tag( 'wp:comment_parent', $comment->comment_parent )
				->tag( 'wp:comment_user_id', $comment->user_id )
				->oxymel( $this->comment_meta( $comment ) )
				->end;
		}
		$oxymel
			->end;
		return $oxymel->to_string();
	}

	public function footer() {
		$oxymel = new Oxymel;
		return $oxymel->close_channel->close_rss->to_string();
	}

	protected function terms( $terms ) {
		$oxymel = new WP_Export_Oxymel;
		foreach( $terms as $term ) {
			$term->parent_slug = $term->parent? $terms[$term->parent]->slug : '';
			$oxymel->tag( 'wp:term' )->contains
				->tag( 'wp:term_id', $term->term_id )
				->tag( 'wp:term_taxonomy', $term->taxonomy )
				->tag( 'wp:term_slug', $term->slug );
			if ( 'nav_menu' != $term->taxonomy ) {
				$oxymel
				->tag( 'wp:term_parent', $term->parent_slug );
			}
				$oxymel
				->optional_cdata( 'wp:term_name', $term->name )
				->optional_cdata( 'wp:term_description', $term->description )
				->end;
		}
		return $oxymel->to_string();
	}

	protected function comment_meta( $comment ) {
		global $wpdb;
		$metas = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->commentmeta WHERE comment_id = %d", $comment->comment_ID ) );
		if ( !$metas ) {
			return new Oxymel;
		}
		$oxymel = new WP_Export_Oxymel;
		foreach( $metas as $meta ) {
			$oxymel->tag( 'wp:commentmeta' )->contains
				->tag( 'wp:meta_key', $meta->meta_key )
				->tag( 'wp:meta_value' )->contains->cdata( $meta->meta_value )->end
			->end;
		}
		return $oxymel;
	}
}
