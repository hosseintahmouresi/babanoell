<?php if ( ! defined( 'ABSPATH' ) ) {exit;} // Exit if accessed directly.

if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
	return;
}

/**
 * WordPress Importer class for managing parsing of WXR files.
 */
if ( ! class_exists( 'WXR_Parser' ) ) {

	class WXR_Parser {
		function parse( $file ) {
			// Attempt to use proper XML parsers first
			if ( extension_loaded( 'simplexml' ) ) {
				$parser = new WXR_Parser_SimpleXML;
				$result = $parser->parse( $file );

				// If SimpleXML succeeds or this is an invalid WXR file then return the results
				if ( ! is_wp_error( $result ) || 'SimpleXML_parse_error' != $result->get_error_code() )
					return $result;
			} else if ( extension_loaded( 'xml' ) ) {
				$parser = new WXR_Parser_XML;
				$result = $parser->parse( $file );

				// If XMLParser succeeds or this is an invalid WXR file then return the results
				if ( ! is_wp_error( $result ) || 'XML_parse_error' != $result->get_error_code() )
					return $result;
			}

			// We have a malformed XML file, so display the error and fallthrough to regex
			if ( isset($result) && defined('IMPORT_DEBUG') && IMPORT_DEBUG ) {
				echo '<pre>';
				if ( 'SimpleXML_parse_error' == $result->get_error_code() ) {
					foreach  ( $result->get_error_data() as $error )
						echo esc_html( $error->line . ':' . $error->column ) . ' ' . esc_html( $error->message ) . "\n";
				} else if ( 'XML_parse_error' == $result->get_error_code() ) {
					$error = $result->get_error_data();
					echo esc_html( $error[0] . ':' . $error[1] ) . ' ' . esc_html( $error[2] );
				}
				echo '</pre>';
				echo '<p><strong>There was an error when reading this WXR file</strong><br />';
				echo 'Details are shown above. The importer will now try again with a different parser...</p>';
			}

			// use regular expressions if nothing else available or this is bad XML
			$parser = new WXR_Parser_Regex;
			return $parser->parse( $file );
		}
	}

	/**
	 * WXR Parser that makes use of the SimpleXML PHP extension.
	 */
	class WXR_Parser_SimpleXML {
		function parse( $file ) {
			$authors = $posts = $categories = $tags = $terms = array();

			$dom = new DOMDocument;

			$wpfs = Codevz_Plus::wpfs();

			$success = $dom->loadXML( $wpfs->get_contents( $file ) );

			if ( ! $success || isset( $dom->doctype ) ) {
				return new WP_Error( 'SimpleXML_parse_error', 'There was an error when reading this WXR file', libxml_get_errors() );
			}

			$xml = simplexml_import_dom( $dom );
			unset( $dom );

			// halt if loading produces an error
			if ( ! $xml )
				return new WP_Error( 'SimpleXML_parse_error', 'There was an error when reading this WXR file', libxml_get_errors() );

			$wxr_version = $xml->xpath('/rss/channel/wp:wxr_version');
			if ( ! $wxr_version )
				return new WP_Error( 'WXR_parse_error', 'This does not appear to be a WXR file, missing/invalid WXR version number' );

			$wxr_version = (string) trim( $wxr_version[0] );
			// confirm that we are dealing with the correct file format
			if ( ! preg_match( '/^\d+\.\d+$/', $wxr_version ) )
				return new WP_Error( 'WXR_parse_error', 'This does not appear to be a WXR file, missing/invalid WXR version number' );

			$base_url = $xml->xpath('/rss/channel/wp:base_site_url');
			$base_url = (string) trim( $base_url[0] );

			$namespaces = $xml->getDocNamespaces();
			if ( ! isset( $namespaces['wp'] ) )
				$namespaces['wp'] = 'http://wordpress.org/export/1.1/';
			if ( ! isset( $namespaces['excerpt'] ) )
				$namespaces['excerpt'] = 'http://wordpress.org/export/1.1/excerpt/';

			// grab authors
			foreach ( $xml->xpath('/rss/channel/wp:author') as $author_arr ) {
				$a = $author_arr->children( $namespaces['wp'] );
				$login = (string) $a->author_login;
				$authors[$login] = array(
					'author_id' => (int) $a->author_id,
					'author_login' => $login,
					'author_email' => (string) $a->author_email,
					'author_display_name' => (string) $a->author_display_name,
					'author_first_name' => (string) $a->author_first_name,
					'author_last_name' => (string) $a->author_last_name
				);
			}

			// grab cats, tags and terms
			foreach ( $xml->xpath('/rss/channel/wp:category') as $term_arr ) {
				$t = $term_arr->children( $namespaces['wp'] );
				$category = array(
					'term_id' => (int) $t->term_id,
					'category_nicename' => (string) $t->category_nicename,
					'category_parent' => (string) $t->category_parent,
					'cat_name' => (string) $t->cat_name,
					'category_description' => (string) $t->category_description
				);

				foreach ( $t->termmeta as $meta ) {
					$category['termmeta'][] = array(
						'key' => (string) $meta->meta_key,
						'value' => (string) $meta->meta_value
					);
				}

				$categories[] = $category;
			}

			foreach ( $xml->xpath('/rss/channel/wp:tag') as $term_arr ) {
				$t = $term_arr->children( $namespaces['wp'] );
				$tag = array(
					'term_id' => (int) $t->term_id,
					'tag_slug' => (string) $t->tag_slug,
					'tag_name' => (string) $t->tag_name,
					'tag_description' => (string) $t->tag_description
				);

				foreach ( $t->termmeta as $meta ) {
					$tag['termmeta'][] = array(
						'key' => (string) $meta->meta_key,
						'value' => (string) $meta->meta_value
					);
				}

				$tags[] = $tag;
			}

			foreach ( $xml->xpath('/rss/channel/wp:term') as $term_arr ) {
				$t = $term_arr->children( $namespaces['wp'] );
				$term = array(
					'term_id' => (int) $t->term_id,
					'term_taxonomy' => (string) $t->term_taxonomy,
					'slug' => (string) $t->term_slug,
					'term_parent' => (string) $t->term_parent,
					'term_name' => (string) $t->term_name,
					'term_description' => (string) $t->term_description
				);

				foreach ( $t->termmeta as $meta ) {
					$term['termmeta'][] = array(
						'key' => (string) $meta->meta_key,
						'value' => (string) $meta->meta_value
					);
				}

				$terms[] = $term;
			}

			// grab posts
			foreach ( $xml->channel->item as $item ) {
				$post = array(
					'post_title' => (string) $item->title,
					'guid' => (string) $item->guid,
				);

				$dc = $item->children( 'http://purl.org/dc/elements/1.1/' );
				$post['post_author'] = (string) $dc->creator;

				$content = $item->children( 'http://purl.org/rss/1.0/modules/content/' );
				$excerpt = $item->children( $namespaces['excerpt'] );
				$post['post_content'] = (string) $content->encoded;
				$post['post_excerpt'] = (string) $excerpt->encoded;

				$wp = $item->children( $namespaces['wp'] );
				$post['post_id'] = (int) $wp->post_id;
				$post['post_date'] = (string) $wp->post_date;
				$post['post_date_gmt'] = (string) $wp->post_date_gmt;
				$post['comment_status'] = (string) $wp->comment_status;
				$post['ping_status'] = (string) $wp->ping_status;
				$post['post_name'] = (string) $wp->post_name;
				$post['status'] = (string) $wp->status;
				$post['post_parent'] = (int) $wp->post_parent;
				$post['menu_order'] = (int) $wp->menu_order;
				$post['post_type'] = (string) $wp->post_type;
				$post['post_password'] = (string) $wp->post_password;
				$post['is_sticky'] = (int) $wp->is_sticky;

				if ( isset($wp->attachment_url) )
					$post['attachment_url'] = (string) $wp->attachment_url;

				foreach ( $item->category as $c ) {
					$att = $c->attributes();
					if ( isset( $att['nicename'] ) )
						$post['terms'][] = array(
							'name' => (string) $c,
							'slug' => (string) $att['nicename'],
							'domain' => (string) $att['domain']
						);
				}

				foreach ( $wp->postmeta as $meta ) {
					$post['postmeta'][] = array(
						'key' => (string) $meta->meta_key,
						'value' => (string) $meta->meta_value
					);
				}

				foreach ( $wp->comment as $comment ) {
					$meta = array();
					if ( isset( $comment->commentmeta ) ) {
						foreach ( $comment->commentmeta as $m ) {
							$meta[] = array(
								'key' => (string) $m->meta_key,
								'value' => (string) $m->meta_value
							);
						}
					}

					$post['comments'][] = array(
						'comment_id' => (int) $comment->comment_id,
						'comment_author' => (string) $comment->comment_author,
						'comment_author_email' => (string) $comment->comment_author_email,
						'comment_author_IP' => (string) $comment->comment_author_IP,
						'comment_author_url' => (string) $comment->comment_author_url,
						'comment_date' => (string) $comment->comment_date,
						'comment_date_gmt' => (string) $comment->comment_date_gmt,
						'comment_content' => (string) $comment->comment_content,
						'comment_approved' => (string) $comment->comment_approved,
						'comment_type' => (string) $comment->comment_type,
						'comment_parent' => (string) $comment->comment_parent,
						'comment_user_id' => (int) $comment->comment_user_id,
						'commentmeta' => $meta,
					);
				}

				$posts[] = $post;
			}

			return array(
				'authors' => $authors,
				'posts' => $posts,
				'categories' => $categories,
				'tags' => $tags,
				'terms' => $terms,
				'base_url' => $base_url,
				'version' => $wxr_version
			);
		}
	}

	/**
	 * WXR Parser that makes use of the XML Parser PHP extension.
	 */
	class WXR_Parser_XML {
		var $wp_tags = array(
			'wp:post_id', 'wp:post_date', 'wp:post_date_gmt', 'wp:comment_status', 'wp:ping_status', 'wp:attachment_url',
			'wp:status', 'wp:post_name', 'wp:post_parent', 'wp:menu_order', 'wp:post_type', 'wp:post_password',
			'wp:is_sticky', 'wp:term_id', 'wp:category_nicename', 'wp:category_parent', 'wp:cat_name', 'wp:category_description',
			'wp:tag_slug', 'wp:tag_name', 'wp:tag_description', 'wp:term_taxonomy', 'wp:term_parent',
			'wp:term_name', 'wp:term_description', 'wp:author_id', 'wp:author_login', 'wp:author_email', 'wp:author_display_name',
			'wp:author_first_name', 'wp:author_last_name',
		);
		var $wp_sub_tags = array(
			'wp:comment_id', 'wp:comment_author', 'wp:comment_author_email', 'wp:comment_author_url',
			'wp:comment_author_IP',	'wp:comment_date', 'wp:comment_date_gmt', 'wp:comment_content',
			'wp:comment_approved', 'wp:comment_type', 'wp:comment_parent', 'wp:comment_user_id',
		);

		function parse( $file ) {
			$this->wxr_version = $this->in_post = $this->cdata = $this->data = $this->sub_data = $this->in_tag = $this->in_sub_tag = false;
			$this->authors = $this->posts = $this->term = $this->category = $this->tag = array();

			$xml = xml_parser_create( 'UTF-8' );
			xml_parser_set_option( $xml, XML_OPTION_SKIP_WHITE, 1 );
			xml_parser_set_option( $xml, XML_OPTION_CASE_FOLDING, 0 );
			xml_set_object( $xml, $this );
			xml_set_character_data_handler( $xml, 'cdata' );
			xml_set_element_handler( $xml, 'tag_open', 'tag_close' );

			$wpfs = Codevz_Plus::wpfs();

			if ( ! xml_parse( $xml, $wpfs->get_contents( $file ), true ) ) {
				$current_line = xml_get_current_line_number( $xml );
				$current_column = xml_get_current_column_number( $xml );
				$error_code = xml_get_error_code( $xml );
				$error_string = xml_error_string( $error_code );
				return new WP_Error( 'XML_parse_error', 'There was an error when reading this WXR file', array( $current_line, $current_column, $error_string ) );
			}
			xml_parser_free( $xml );

			if ( ! preg_match( '/^\d+\.\d+$/', $this->wxr_version ) )
				return new WP_Error( 'WXR_parse_error', 'This does not appear to be a WXR file, missing/invalid WXR version number' );

			return array(
				'authors' => $this->authors,
				'posts' => $this->posts,
				'categories' => $this->category,
				'tags' => $this->tag,
				'terms' => $this->term,
				'base_url' => $this->base_url,
				'version' => $this->wxr_version
			);
		}

		function tag_open( $parse, $tag, $attr ) {
			if ( in_array( $tag, $this->wp_tags ) ) {
				$this->in_tag = substr( $tag, 3 );
				return;
			}

			if ( in_array( $tag, $this->wp_sub_tags ) ) {
				$this->in_sub_tag = substr( $tag, 3 );
				return;
			}

			switch ( $tag ) {
				case 'category':
					if ( isset($attr['domain'], $attr['nicename']) ) {
						$this->sub_data['domain'] = $attr['domain'];
						$this->sub_data['slug'] = $attr['nicename'];
					}
					break;
				case 'item': $this->in_post = true;
				case 'title': if ( $this->in_post ) $this->in_tag = 'post_title'; break;
				case 'guid': $this->in_tag = 'guid'; break;
				case 'dc:creator': $this->in_tag = 'post_author'; break;
				case 'content:encoded': $this->in_tag = 'post_content'; break;
				case 'excerpt:encoded': $this->in_tag = 'post_excerpt'; break;

				case 'wp:term_slug': $this->in_tag = 'slug'; break;
				case 'wp:meta_key': $this->in_sub_tag = 'key'; break;
				case 'wp:meta_value': $this->in_sub_tag = 'value'; break;
			}
		}

		function cdata( $parser, $cdata ) {
			if ( ! trim( $cdata ) )
				return;

			if ( false !== $this->in_tag || false !== $this->in_sub_tag ) {
				$this->cdata .= $cdata;
			} else {
				$this->cdata .= trim( $cdata );
			}
		}

		function tag_close( $parser, $tag ) {
			switch ( $tag ) {
				case 'wp:comment':
					unset( $this->sub_data['key'], $this->sub_data['value'] ); // remove meta sub_data
					if ( ! empty( $this->sub_data ) )
						$this->data['comments'][] = $this->sub_data;
					$this->sub_data = false;
					break;
				case 'wp:commentmeta':
					$this->sub_data['commentmeta'][] = array(
						'key' => $this->sub_data['key'],
						'value' => $this->sub_data['value']
					);
					break;
				case 'category':
					if ( ! empty( $this->sub_data ) ) {
						$this->sub_data['name'] = $this->cdata;
						$this->data['terms'][] = $this->sub_data;
					}
					$this->sub_data = false;
					break;
				case 'wp:postmeta':
					if ( ! empty( $this->sub_data ) )
						$this->data['postmeta'][] = $this->sub_data;
					$this->sub_data = false;
					break;
				case 'item':
					$this->posts[] = $this->data;
					$this->data = false;
					break;
				case 'wp:category':
				case 'wp:tag':
				case 'wp:term':
					$n = substr( $tag, 3 );
					array_push( $this->$n, $this->data );
					$this->data = false;
					break;
				case 'wp:author':
					if ( ! empty($this->data['author_login']) )
						$this->authors[$this->data['author_login']] = $this->data;
					$this->data = false;
					break;
				case 'wp:base_site_url':
					$this->base_url = $this->cdata;
					break;
				case 'wp:wxr_version':
					$this->wxr_version = $this->cdata;
					break;

				default:
					if ( $this->in_sub_tag ) {
						$this->sub_data[$this->in_sub_tag] = ! empty( $this->cdata ) ? $this->cdata : '';
						$this->in_sub_tag = false;
					} else if ( $this->in_tag ) {
						$this->data[$this->in_tag] = ! empty( $this->cdata ) ? $this->cdata : '';
						$this->in_tag = false;
					}
			}

			$this->cdata = false;
		}
	}

	/**
	 * WXR Parser that uses regular expressions. Fallback for installs without an XML parser.
	 */
	class WXR_Parser_Regex {
		var $authors = array();
		var $posts = array();
		var $categories = array();
		var $tags = array();
		var $terms = array();
		var $base_url = '';

		function __construct() {}

		function parse( $file ) {

			$wxr_version = $in_multiline = false;
			$multiline_content = '';

			$multiline_tags = array(
				'item'        => array( 'posts', array( $this, 'process_post' ) ),
				'wp:category' => array( 'categories', array( $this, 'process_category' ) ),
				'wp:tag'      => array( 'tags', array( $this, 'process_tag' ) ),
				'wp:term'     => array( 'terms', array( $this, 'process_term' ) ),
			);

			$wpfs = Codevz_Plus::wpfs();
			$file_content = $wpfs->get_contents( $file );

			if ( $file_content ) {
				$file_lines = explode( "\n", $file_content );

				foreach ( $file_lines as $importline ) {
					$importline = rtrim( $importline );

					if ( ! $wxr_version && preg_match( '|<wp:wxr_version>(\d+\.\d+)</wp:wxr_version>|', $importline, $version ) ) {
						$wxr_version = $version[1];
					}

					if ( false !== strpos( $importline, '<wp:base_site_url>' ) ) {
						preg_match( '|<wp:base_site_url>(.*?)</wp:base_site_url>|is', $importline, $url );
						$this->base_url = $url[1];
						continue;
					}

					if ( false !== strpos( $importline, '<wp:author>' ) ) {
						preg_match( '|<wp:author>(.*?)</wp:author>|is', $importline, $author );
						$a = $this->process_author( $author[1] );
						$this->authors[$a['author_login']] = $a;
						continue;
					}

					foreach ( $multiline_tags as $tag => $handler ) {
						// Handle multi-line tags on a singular line
						if ( preg_match( '|<' . $tag . '>(.*?)</' . $tag . '>|is', $importline, $matches ) ) {
							$this->{$handler[0]}[] = call_user_func( $handler[1], $matches[1] );

						} elseif ( false !== ( $pos = strpos( $importline, "<$tag>" ) ) ) {
							// Take note of any content after the opening tag
							$multiline_content = trim( substr( $importline, $pos + strlen( $tag ) + 2 ) );

							// We don't want to have this line added to `$is_multiline` below.
							$importline = '';
							$in_multiline = $tag;

						} elseif ( false !== ( $pos = strpos( $importline, "</$tag>" ) ) ) {
							$in_multiline = false;
							$multiline_content .= trim( substr( $importline, 0, $pos ) );

							$this->{$handler[0]}[] = call_user_func( $handler[1], $multiline_content );
						}
					}

					if ( $in_multiline && $importline ) {
						$multiline_content .= $importline . "\n";
					}
				}
			}

			if ( ! $wxr_version ) {
				return new WP_Error( 'WXR_parse_error', 'This does not appear to be a WXR file, missing/invalid WXR version number' );
			}

			return array(
				'authors' => $this->authors,
				'posts' => $this->posts,
				'categories' => $this->categories,
				'tags' => $this->tags,
				'terms' => $this->terms,
				'base_url' => $this->base_url,
				'version' => $wxr_version,
			);
		}

		function get_tag( $string, $tag ) {
			preg_match( "|<$tag.*?>(.*?)</$tag>|is", $string, $return );
			if ( isset( $return[1] ) ) {
				if ( substr( $return[1], 0, 9 ) == '<![CDATA[' ) {
					if ( strpos( $return[1], ']]]]><![CDATA[>' ) !== false ) {
						preg_match_all( '|<!\[CDATA\[(.*?)\]\]>|s', $return[1], $matches );
						$return = '';
						foreach( $matches[1] as $match )
							$return .= $match;
					} else {
						$return = preg_replace( '|^<!\[CDATA\[(.*)\]\]>$|s', '$1', $return[1] );
					}
				} else {
					$return = $return[1];
				}
			} else {
				$return = '';
			}
			return $return;
		}

		function process_category( $c ) {
			return array(
				'term_id' => $this->get_tag( $c, 'wp:term_id' ),
				'cat_name' => $this->get_tag( $c, 'wp:cat_name' ),
				'category_nicename'	=> $this->get_tag( $c, 'wp:category_nicename' ),
				'category_parent' => $this->get_tag( $c, 'wp:category_parent' ),
				'category_description' => $this->get_tag( $c, 'wp:category_description' ),
			);
		}

		function process_tag( $t ) {
			return array(
				'term_id' => $this->get_tag( $t, 'wp:term_id' ),
				'tag_name' => $this->get_tag( $t, 'wp:tag_name' ),
				'tag_slug' => $this->get_tag( $t, 'wp:tag_slug' ),
				'tag_description' => $this->get_tag( $t, 'wp:tag_description' ),
			);
		}

		function process_term( $t ) {
			return array(
				'term_id' => $this->get_tag( $t, 'wp:term_id' ),
				'term_taxonomy' => $this->get_tag( $t, 'wp:term_taxonomy' ),
				'slug' => $this->get_tag( $t, 'wp:term_slug' ),
				'term_parent' => $this->get_tag( $t, 'wp:term_parent' ),
				'term_name' => $this->get_tag( $t, 'wp:term_name' ),
				'term_description' => $this->get_tag( $t, 'wp:term_description' ),
			);
		}

		function process_author( $a ) {
			return array(
				'author_id' => $this->get_tag( $a, 'wp:author_id' ),
				'author_login' => $this->get_tag( $a, 'wp:author_login' ),
				'author_email' => $this->get_tag( $a, 'wp:author_email' ),
				'author_display_name' => $this->get_tag( $a, 'wp:author_display_name' ),
				'author_first_name' => $this->get_tag( $a, 'wp:author_first_name' ),
				'author_last_name' => $this->get_tag( $a, 'wp:author_last_name' ),
			);
		}

		function process_post( $post ) {
			$post_id        = $this->get_tag( $post, 'wp:post_id' );
			$post_title     = $this->get_tag( $post, 'title' );
			$post_date      = $this->get_tag( $post, 'wp:post_date' );
			$post_date_gmt  = $this->get_tag( $post, 'wp:post_date_gmt' );
			$comment_status = $this->get_tag( $post, 'wp:comment_status' );
			$ping_status    = $this->get_tag( $post, 'wp:ping_status' );
			$status         = $this->get_tag( $post, 'wp:status' );
			$post_name      = $this->get_tag( $post, 'wp:post_name' );
			$post_parent    = $this->get_tag( $post, 'wp:post_parent' );
			$menu_order     = $this->get_tag( $post, 'wp:menu_order' );
			$post_type      = $this->get_tag( $post, 'wp:post_type' );
			$post_password  = $this->get_tag( $post, 'wp:post_password' );
			$is_sticky      = $this->get_tag( $post, 'wp:is_sticky' );
			$guid           = $this->get_tag( $post, 'guid' );
			$post_author    = $this->get_tag( $post, 'dc:creator' );

			$post_excerpt = $this->get_tag( $post, 'excerpt:encoded' );
			$post_excerpt = preg_replace_callback( '|<(/?[A-Z]+)|', array( &$this, '_normalize_tag' ), $post_excerpt );
			$post_excerpt = str_replace( '<br>', '<br />', $post_excerpt );
			$post_excerpt = str_replace( '<hr>', '<hr />', $post_excerpt );

			$post_content = $this->get_tag( $post, 'content:encoded' );
			$post_content = preg_replace_callback( '|<(/?[A-Z]+)|', array( &$this, '_normalize_tag' ), $post_content );
			$post_content = str_replace( '<br>', '<br />', $post_content );
			$post_content = str_replace( '<hr>', '<hr />', $post_content );

			$postdata = compact( 'post_id', 'post_author', 'post_date', 'post_date_gmt', 'post_content', 'post_excerpt',
				'post_title', 'status', 'post_name', 'comment_status', 'ping_status', 'guid', 'post_parent',
				'menu_order', 'post_type', 'post_password', 'is_sticky'
			);

			$attachment_url = $this->get_tag( $post, 'wp:attachment_url' );
			if ( $attachment_url )
				$postdata['attachment_url'] = $attachment_url;

			preg_match_all( '|<category domain="([^"]+?)" nicename="([^"]+?)">(.+?)</category>|is', $post, $terms, PREG_SET_ORDER );
			foreach ( $terms as $t ) {
				$post_terms[] = array(
					'slug' => $t[2],
					'domain' => $t[1],
					'name' => str_replace( array( '<![CDATA[', ']]>' ), '', $t[3] ),
				);
			}
			if ( ! empty( $post_terms ) ) $postdata['terms'] = $post_terms;

			preg_match_all( '|<wp:comment>(.+?)</wp:comment>|is', $post, $comments );
			$comments = $comments[1];
			if ( $comments ) {
				foreach ( $comments as $comment ) {
					preg_match_all( '|<wp:commentmeta>(.+?)</wp:commentmeta>|is', $comment, $commentmeta );
					$commentmeta = $commentmeta[1];
					$c_meta = array();
					foreach ( $commentmeta as $m ) {
						$c_meta[] = array(
							'key' => $this->get_tag( $m, 'wp:meta_key' ),
							'value' => $this->get_tag( $m, 'wp:meta_value' ),
						);
					}

					$post_comments[] = array(
						'comment_id' => $this->get_tag( $comment, 'wp:comment_id' ),
						'comment_author' => $this->get_tag( $comment, 'wp:comment_author' ),
						'comment_author_email' => $this->get_tag( $comment, 'wp:comment_author_email' ),
						'comment_author_IP' => $this->get_tag( $comment, 'wp:comment_author_IP' ),
						'comment_author_url' => $this->get_tag( $comment, 'wp:comment_author_url' ),
						'comment_date' => $this->get_tag( $comment, 'wp:comment_date' ),
						'comment_date_gmt' => $this->get_tag( $comment, 'wp:comment_date_gmt' ),
						'comment_content' => $this->get_tag( $comment, 'wp:comment_content' ),
						'comment_approved' => $this->get_tag( $comment, 'wp:comment_approved' ),
						'comment_type' => $this->get_tag( $comment, 'wp:comment_type' ),
						'comment_parent' => $this->get_tag( $comment, 'wp:comment_parent' ),
						'comment_user_id' => $this->get_tag( $comment, 'wp:comment_user_id' ),
						'commentmeta' => $c_meta,
					);
				}
			}
			if ( ! empty( $post_comments ) ) $postdata['comments'] = $post_comments;

			preg_match_all( '|<wp:postmeta>(.+?)</wp:postmeta>|is', $post, $postmeta );
			$postmeta = $postmeta[1];
			if ( $postmeta ) {
				foreach ( $postmeta as $p ) {
					$post_postmeta[] = array(
						'key' => $this->get_tag( $p, 'wp:meta_key' ),
						'value' => $this->get_tag( $p, 'wp:meta_value' ),
					);
				}
			}
			if ( ! empty( $post_postmeta ) ) $postdata['postmeta'] = $post_postmeta;

			return $postdata;
		}

		function _normalize_tag( $matches ) {
			return '<' . strtolower( $matches[1] );
		}

	}

}

/**
 * WordPress Importer class for managing the import process of a WXR file
 */
if ( class_exists( 'WP_Importer' ) ) {

	class Codevz_Importer extends WP_Importer {

		var $max_wxr_version = 1.2; // max. supported WXR version

		var $id; // WXR attachment ID

		// information to import from WXR file
		var $version;
		var $authors = array();
		var $posts = array();
		var $terms = array();
		var $categories = array();
		var $tags = array();
		var $base_url = '';

		// mappings from old information to new
		var $processed_authors = array();
		var $author_mapping = array();
		var $processed_terms = array();
		var $processed_posts = array();
		var $post_orphans = array();
		var $processed_menu_items = array();
		var $menu_item_orphans = array();
		var $missing_menu_items = array();

		var $fetch_attachments = false;
		var $url_remap = array();
		var $featured_images = array();

		/**
		 * Registered callback function for the WordPress Importer
		 *
		 * Manages the three separate stages of the WXR import process
		 */
		function dispatch() {
			$this->header();

			$step = empty( $_GET['step'] ) ? 0 : (int) $_GET['step'];
			switch ( $step ) {
				case 0:
					$this->greet();
					break;
				case 1:
					check_admin_referer( 'import-upload' );
					if ( $this->handle_upload() )
						$this->import_options();
					break;
				case 2:
					check_admin_referer( 'import-wordpress' );
					$this->fetch_attachments = ( ! empty( $_POST['fetch_attachments'] ) && $this->allow_fetch_attachments() );
					$this->id = (int) $_POST['import_id'];
					$file = get_attached_file( $this->id );
					set_time_limit(0);
					$this->import( $file );
					break;
			}

			$this->footer();
		}

		/**
		 * The main controller for the actual import stage.
		 *
		 * @param string $file Path to the WXR file for importing
		 * CODEVZ
		 */
		function import( $file, $x = 0 ) {
			ob_start();
			add_filter( 'import_post_meta_key', array( $this, 'is_valid_meta_key' ) );
			add_filter( 'http_request_timeout', array( &$this, 'bump_request_timeout' ) );

			$this->import_start( $file );
			$this->get_author_mapping();

			wp_suspend_cache_invalidation( true );

			// First import, end only check for menus
			if ( $x == 1 || $x >= count( $this->posts ) ) {
				$this->process_categories();
				$this->process_tags();
				$this->process_terms();
			}

			// Import posts
			$x = $this->process_posts( $x );

			wp_suspend_cache_invalidation( false );

			// update incorrect/missing information in the DB
			$this->backfill_parents();
			$this->backfill_attachment_urls();
			$this->remap_featured_images();

			$this->import_end();
			$z = ob_get_clean();

			return $x;
		}

		/**
		 * Parses the WXR file and prepares us for the task of processing parsed data
		 *
		 * @param string $file Path to the WXR file for importing
		 */
		function import_start( $file ) {

			$import_data = $this->parse( $file );

			if ( is_wp_error( $import_data ) ) {
				echo '<p><strong>Sorry, there has been an error.</strong><br />';
				echo esc_html( $import_data->get_error_message() ) . '</p>';
				$this->footer();
				wp_die();
			}

			$this->version = $import_data['version'];
			$this->get_authors_from_import( $import_data );
			$this->posts = $import_data['posts'];
			$this->terms = $import_data['terms'];
			$this->categories = $import_data['categories'];
			$this->tags = $import_data['tags'];
			$this->base_url = esc_url( $import_data['base_url'] );

			wp_defer_term_counting( true );
			wp_defer_comment_counting( true );

			do_action( 'import_start' );
		}

		/**
		 * Performs post-import cleanup of files and the cache
		 */
		function import_end() {
			wp_import_cleanup( $this->id );

			wp_cache_flush();
			foreach ( get_taxonomies() as $tax ) {
				delete_option( "{$tax}_children" );
				_get_term_hierarchy( $tax );
			}

			wp_defer_term_counting( false );
			wp_defer_comment_counting( false );

			echo '<p>All done. <a href="' . esc_url( admin_url() ) . '">Have fun!</a>' . '</p>';
			echo '<p>Remember to update the passwords and roles of imported users.</p>';

			do_action( 'import_end' );
		}

		/**
		 * Handles the WXR upload and initial parsing of the file to prepare for
		 * displaying author import options
		 *
		 * @return bool False if error uploading or invalid file, true otherwise
		 */
		function handle_upload() {
			$file = wp_import_handle_upload();

			if ( isset( $file['error'] ) ) {
				echo '<p><strong>Sorry, there has been an error.</strong><br />';
				echo esc_html( $file['error'] ) . '</p>';
				return false;
			} else if ( ! file_exists( $file['file'] ) ) {
				echo '<p><strong>Sorry, there has been an error.</strong><br />';
				/* translators: 1. File url */
				printf( 'The export file could not be found at <code>%s</code>. It is likely that this was caused by a permissions problem.', esc_html( $file['file'] ) );
				echo '</p>';
				return false;
			}

			$this->id = (int) $file['id'];
			$import_data = $this->parse( $file['file'] );
			if ( is_wp_error( $import_data ) ) {
				echo '<p><strong>Sorry, there has been an error.</strong><br />';
				echo esc_html( $import_data->get_error_message() ) . '</p>';
				return false;
			}

			$this->version = $import_data['version'];
			if ( $this->version > $this->max_wxr_version ) {
				echo '<div class="error"><p><strong>';
				/* translators: 1. Version number */
				printf( 'This WXR file (version %s) may not be supported by this version of the importer. Please consider updating.', esc_html($import_data['version']) );
				echo '</strong></p></div>';
			}

			$this->get_authors_from_import( $import_data );

			return true;
		}

		/**
		 * Retrieve authors from parsed WXR data
		 *
		 * Uses the provided author information from WXR 1.1 files
		 * or extracts info from each post for WXR 1.0 files
		 *
		 * @param array $import_data Data returned by a WXR parser
		 */
		function get_authors_from_import( $import_data ) {
			if ( ! empty( $import_data['authors'] ) ) {
				$this->authors = $import_data['authors'];
			// no author information, grab it from the posts
			} else {
				foreach ( $import_data['posts'] as $post ) {
					$login = sanitize_user( $post['post_author'], true );
					if ( empty( $login ) ) {
						/* translators: 1. post author name */
						printf( 'Failed to import author %s. Their posts will be attributed to the current user.', esc_html( $post['post_author'] ) );
						echo '<br />';
						continue;
					}

					if ( ! isset($this->authors[$login]) )
						$this->authors[$login] = array(
							'author_login' => $login,
							'author_display_name' => $post['post_author']
						);
				}
			}
		}

		/**
		 * Display pre-import options, author importing/mapping and option to
		 * fetch attachments
		 */
		function import_options() {
			$j = 0;
	?>
	<form action="<?php echo esc_url( admin_url( 'admin.php?import=wordpress&amp;step=2' ) ); ?>" method="post">
		<?php wp_nonce_field( 'import-wordpress' ); ?>
		<input type="hidden" name="import_id" value="<?php echo esc_attr( $this->id ); ?>" />

	<?php if ( ! empty( $this->authors ) ) : ?>
		<h3>Assign Authors</h3>
		<p>To make it easier for you to edit and save the imported content, you may want to reassign the author of the imported item to an existing user of this site. For example, you may want to import all the entries as <code>admin</code>s entries.</p>
	<?php if ( $this->allow_create_users() ) : ?>
		<p>...</p>
	<?php endif; ?>
		<ol id="authors">
	<?php foreach ( $this->authors as $author ) : ?>
			<li><?php $this->author_select( $j++, $author ); ?></li>
	<?php endforeach; ?>
		</ol>
	<?php endif; ?>

	<?php if ( $this->allow_fetch_attachments() ) : ?>
		<h3>Import Attachments</h3>
		<p>
			<input type="checkbox" value="1" name="fetch_attachments" id="import-attachments" />
			<label for="import-attachments">Download and import file attachments</label>
		</p>
	<?php endif; ?>

		<p class="submit"><input type="submit" class="button" value="Submit" /></p>
	</form>
	<?php
		}

		/**
		 * Display import options for an individual author. That is, either create
		 * a new user based on import info or map to an existing user
		 *
		 * @param int $n Index for each author in the form
		 * @param array $author Author information, e.g. login, display name, email
		 */
		function author_select( $n, $author ) {
			echo ' <strong>' . esc_html( $author['author_display_name'] );
			if ( $this->version != '1.0' ) echo ' (' . esc_html( $author['author_login'] ) . ')';
			echo '</strong><br />';

			if ( $this->version != '1.0' )
				echo '<div style="margin-left:18px">';

			$create_users = $this->allow_create_users();
			if ( $create_users ) {
				if ( $this->version != '1.0' ) {
					$value = '';
				} else {
					$value = esc_attr( sanitize_user( $author['author_login'], true ) );
				}

				echo ' <input type="text" name="user_new['.esc_html( $n ).']" value="'. esc_html( $value ) .'" /><br />';
			}

			if ( ! $create_users && $this->version == '1.0' )
				echo 'assign posts to an existing user:';
			else
				echo 'or assign posts to an existing user:';
			wp_dropdown_users( array( 'name' => "user_map[$n]", 'multi' => true, 'show_option_all' => 'Select' ) );
			echo '<input type="hidden" name="imported_authors['.esc_attr( $n ).']" value="' . esc_attr( $author['author_login'] ) . '" />';

			if ( $this->version != '1.0' )
				echo '</div>';
		}

		/**
		 * Map old author logins to local user IDs based on decisions made
		 * in import options form. Can map to an existing user, create a new user
		 * or falls back to the current user in case of error with either of the previous
		 */
		function get_author_mapping() {

			$authors  = filter_input( INPUT_POST, 'imported_authors', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

			if ( ! $authors ) {
				return;
			}

			$user_map = filter_input( INPUT_POST, 'user_map', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$user_new = filter_input( INPUT_POST, 'user_new', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

			$create_users = $this->allow_create_users();

			foreach( (array) $authors as $i => $old_login ) {
				// Multisite adds strtolower to sanitize_user. Need to sanitize here to stop breakage in process_posts.
				$santized_old_login = sanitize_user( $old_login, true );
				$old_id = isset( $this->authors[$old_login]['author_id'] ) ? intval($this->authors[$old_login]['author_id']) : false;

				if ( ! empty( $user_map[$i] ) ) {
					$user = get_userdata( intval( $user_map[$i] ) );
					if ( isset( $user->ID ) ) {
						if ( $old_id )
							$this->processed_authors[$old_id] = $user->ID;
						$this->author_mapping[$santized_old_login] = $user->ID;
					}
				} else if ( $create_users ) {
					if ( ! empty( $user_new[$i] ) ) {
						$user_id = wp_create_user( $user_new[$i], wp_generate_password() );
					} else if ( $this->version != '1.0' ) {
						$user_data = array(
							'user_login' => $old_login,
							'user_pass' => wp_generate_password(),
							'user_email' => isset( $this->authors[$old_login]['author_email'] ) ? $this->authors[$old_login]['author_email'] : '',
							'display_name' => $this->authors[$old_login]['author_display_name'],
							'first_name' => isset( $this->authors[$old_login]['author_first_name'] ) ? $this->authors[$old_login]['author_first_name'] : '',
							'last_name' => isset( $this->authors[$old_login]['author_last_name'] ) ? $this->authors[$old_login]['author_last_name'] : '',
						);
						$user_id = wp_insert_user( $user_data );
					}

					if ( ! is_wp_error( $user_id ) ) {
						if ( $old_id )
							$this->processed_authors[$old_id] = $user_id;
						$this->author_mapping[$santized_old_login] = $user_id;
					} else {
						/* translators: 1. Author name */
						printf( 'Failed to create new user for %s. Their posts will be attributed to the current user.', esc_html($this->authors[$old_login]['author_display_name']) );
						if ( defined('IMPORT_DEBUG') && IMPORT_DEBUG )
							echo ' ' . esc_html( $user_id->get_error_message() );
						echo '<br />';
					}
				}

				// failsafe: if the user_id was invalid, default to the current user
				if ( ! isset( $this->author_mapping[$santized_old_login] ) ) {
					if ( $old_id )
						$this->processed_authors[$old_id] = (int) get_current_user_id();
					$this->author_mapping[$santized_old_login] = (int) get_current_user_id();
				}
			}
		}

		/**
		 * Create new categories based on import information
		 *
		 * Doesn't create a new category if its slug already exists
		 */
		function process_categories() {
			$this->categories = apply_filters( 'wp_import_categories', $this->categories );

			if ( empty( $this->categories ) )
				return;

			foreach ( $this->categories as $cat ) {
				// if the category already exists leave it alone
				$term_id = term_exists( $cat['category_nicename'], 'category' );
				if ( $term_id ) {
					if ( is_array($term_id) ) $term_id = $term_id['term_id'];
					if ( isset($cat['term_id']) )
						$this->processed_terms[intval($cat['term_id'])] = (int) $term_id;
					continue;
				}

				$category_parent = empty( $cat['category_parent'] ) ? 0 : category_exists( $cat['category_parent'] );
				$category_description = isset( $cat['category_description'] ) ? $cat['category_description'] : '';
				$catarr = array(
					'category_nicename' => $cat['category_nicename'],
					'category_parent' => $category_parent,
					'cat_name' => $cat['cat_name'],
					'category_description' => $category_description
				);
				$catarr = wp_slash( $catarr );

				$id = wp_insert_category( $catarr );
				if ( ! is_wp_error( $id ) ) {

					if ( isset($cat['term_id']) ) {

						$this->processed_terms[intval($cat['term_id'])] = $id;

						// Save imported.
						Codevz_Demo_Importer::save( 'terms', [ 'id' => $id, 'taxonomy' => 'category' ] );

					}
				} else {
					/* translators: 1. Category name */
					printf( 'Failed to import category %s', esc_html($cat['category_nicename']) );
					if ( defined('IMPORT_DEBUG') && IMPORT_DEBUG )
						echo ': ' . esc_html( $id->get_error_message() );
					echo '<br />';
					continue;
				}

				if ( isset( $id['term_id'] ) ) {
					$this->process_termmeta( $cat, $id['term_id'] );
				}
			}

			unset( $this->categories );
		}

		/**
		 * Create new post tags based on import information
		 *
		 * Doesn't create a tag if its slug already exists
		 */
		function process_tags() {
			$this->tags = apply_filters( 'wp_import_tags', $this->tags );

			if ( empty( $this->tags ) )
				return;

			foreach ( $this->tags as $tag ) {
				// if the tag already exists leave it alone
				$term_id = term_exists( $tag['tag_slug'], 'post_tag' );
				if ( $term_id ) {
					if ( is_array($term_id) ) $term_id = $term_id['term_id'];
					if ( isset($tag['term_id']) )
						$this->processed_terms[intval($tag['term_id'])] = (int) $term_id;
					continue;
				}

				$tag = wp_slash( $tag );
				$tag_desc = isset( $tag['tag_description'] ) ? $tag['tag_description'] : '';
				$tagarr = array( 'slug' => $tag['tag_slug'], 'description' => $tag_desc );

				$id = wp_insert_term( $tag['tag_name'], 'post_tag', $tagarr );
				if ( ! is_wp_error( $id ) ) {
					
					if ( isset($tag['term_id']) ) {
						$this->processed_terms[intval($tag['term_id'])] = $id['term_id'];

						// Save imported.
						Codevz_Demo_Importer::save( 'terms', [ 'id' => $id['term_id'], 'taxonomy' => 'post_tag' ] );

					}

				} else {
					/* translators: 1. Tag name */
					printf( 'Failed to import post tag %s', esc_html($tag['tag_name']) );
					if ( defined('IMPORT_DEBUG') && IMPORT_DEBUG )
						echo ': ' . esc_html( $id->get_error_message() );
					echo '<br />';
					continue;
				}

				$this->process_termmeta( $tag, $id['term_id'] );
			}

			unset( $this->tags );
		}

		/**
		 * Create new terms based on import information
		 *
		 * Doesn't create a term its slug already exists
		 */
		function process_terms() {
			$this->terms = apply_filters( 'wp_import_terms', $this->terms );

			if ( empty( $this->terms ) )
				return;

			foreach ( $this->terms as $term ) {
				// if the term already exists in the correct taxonomy leave it alone
				$term_id = term_exists( $term['slug'], $term['term_taxonomy'] );
				if ( $term_id ) {
					if ( is_array($term_id) ) $term_id = $term_id['term_id'];
					if ( isset($term['term_id']) )
						$this->processed_terms[intval($term['term_id'])] = (int) $term_id;
					continue;
				}

				if ( empty( $term['term_parent'] ) ) {
					$parent = 0;
				} else {
					$parent = term_exists( $term['term_parent'], $term['term_taxonomy'] );
					if ( is_array( $parent ) ) $parent = $parent['term_id'];
				}
				$term = wp_slash( $term );
				$description = isset( $term['term_description'] ) ? $term['term_description'] : '';
				$termarr = array( 'slug' => $term['slug'], 'description' => $description, 'parent' => intval($parent) );

				$id = wp_insert_term( $term['term_name'], $term['term_taxonomy'], $termarr );
				if ( ! is_wp_error( $id ) ) {

					if ( isset($term['term_id']) ) {

						$this->processed_terms[intval($term['term_id'])] = $id['term_id'];

						// Save imported.
						Codevz_Demo_Importer::save( 'terms', [ 'id' => $id['term_id'], 'taxonomy' => $term['term_taxonomy'] ] );

					}
				} else {
					/* translators: 1. Taxonomt 2. Term name */
					printf( 'Failed to import %1$s %2$s', esc_html($term['term_taxonomy']), esc_html($term['term_name']) );
					if ( defined('IMPORT_DEBUG') && IMPORT_DEBUG )
						echo ': ' . esc_html( $id->get_error_message() );
					echo '<br />';
					continue;
				}

				$this->process_termmeta( $term, $id['term_id'] );
			}

			unset( $this->terms );
		}

		/**
		 * Add metadata to imported term.
		 *
		 * @since 0.6.2
		 *
		 * @param array $term    Term data from WXR import.
		 * @param int   $term_id ID of the newly created term.
		 */
		protected function process_termmeta( $term, $term_id ) {
			if ( ! function_exists( 'add_term_meta' ) ) {
				return;
			}

			if ( ! isset( $term['termmeta'] ) ) {
				$term['termmeta'] = array();
			}

			/**
			 * Filters the metadata attached to an imported term.
			 *
			 * @since 0.6.2
			 *
			 * @param array $termmeta Array of term meta.
			 * @param int   $term_id  ID of the newly created term.
			 * @param array $term     Term data from the WXR import.
			 */
			$term['termmeta'] = apply_filters( 'wp_import_term_meta', $term['termmeta'], $term_id, $term );

			if ( empty( $term['termmeta'] ) ) {
				return;
			}

			foreach ( $term['termmeta'] as $meta ) {
				/**
				 * Filters the meta key for an imported piece of term meta.
				 *
				 * @since 0.6.2
				 *
				 * @param string $meta_key Meta key.
				 * @param int    $term_id  ID of the newly created term.
				 * @param array  $term     Term data from the WXR import.
				 */
				$key = apply_filters( 'import_term_meta_key', $meta['key'], $term_id, $term );
				if ( ! $key ) {
					continue;
				}

				// Export gets meta straight from the DB so could have a serialized string
				$value = maybe_unserialize( $meta['value'] );

				add_term_meta( $term_id, wp_slash( $key ), wp_slash_strings_only( $value ) );

				/**
				 * Fires after term meta is imported.
				 *
				 * @since 0.6.2
				 *
				 * @param int    $term_id ID of the newly created term.
				 * @param string $key     Meta key.
				 * @param mixed  $value   Meta value.
				 */
				do_action( 'import_term_meta', $term_id, $key, $value );
			}
		}

		/**
		 * Create new posts based on import information
		 *
		 * Posts marked as having a parent which doesn't exist will become top level items.
		 * Doesn't create a new post if: the post type doesn't exist, the given post ID
		 * is already noted as imported or a post with the same title and date already exists.
		 * Note that new/updated terms, comments and meta are imported for the last of the above.
		 * CODEVZ
		 */
		function process_posts( $x = 0 ) {
			$this->posts = apply_filters( 'wp_import_posts', $this->posts );

			// The last imoprt step
			if ( $x >= count( $this->posts ) ) {

				// Import only menus skip others
				foreach ( $this->posts as $post ) {

					if ( $post['post_type'] == 'nav_menu_item' ) {
						$this->process_menu_item( $post );
						continue;
					}

				}

				// Exit import
				return 'done';
			}

			// The first time
			if ( $x === 1 ) {
				$x = 0;
				$j = 0;
			} else {
				$j = $x;
			}

			// Slice posts array between two range
			$this->posts = array_slice( $this->posts, $x, $x + 4 );

			$i = 0;
			// Start import
			foreach ( $this->posts as $post ) {
				$j++;
				$post = apply_filters( 'wp_import_post_data_raw', $post );

				if ( ! post_type_exists( $post['post_type'] ) ) {
					continue;
				}

				if ( isset( $this->processed_posts[$post['post_id']] ) && ! empty( $post['post_id'] ) ) {
					continue;
				}

				// Skip menus, attachment, Sample Page
				if ( $post['status'] == 'auto-draft' || $post['post_type'] == 'custom_css' || $post['post_type'] == 'attachment' || $post['post_type'] == 'nav_menu_item' || $post['post_title'] == 'Sample Page' || $post['post_title'] == 'Hello World' ) {
					continue;
				}

				$post_type_object = get_post_type_object( $post['post_type'] );
				$post_exists = post_exists( $post['post_title'], '', $post['post_date'] );

				/**
				* Filter ID of the existing post corresponding to post currently importing.
				*
				* Return 0 to force the post to be imported. Filter the ID to be something else
				* to override which existing post is mapped to the imported post.
				*
				* @see post_exists()
				* @since 0.6.2
				*
				* @param int   $post_exists  Post ID, or 0 if post did not exist.
				* @param array $post         The post array to be inserted.
				*/
				$post_exists = apply_filters( 'wp_import_existing_post', $post_exists, $post );

				//if ( $post_exists && get_post_type( $post_exists ) == $post['post_type'] ) {
					/* translators: 1. post type name 2. post title */
				//	printf( '%1$s &#8220;%2$s&#8221; already exists.', esc_html( $post_type_object->labels->singular_name ), esc_html($post['post_title']) );
				//	echo '<br />';
				//	$comment_post_ID = $post_id = $post_exists;
				//	$this->processed_posts[ intval( $post['post_id'] ) ] = intval( $post_exists );
				//} else {
					$post_parent = (int) $post['post_parent'];
					if ( $post_parent ) {
						// if we already know the parent, map it to the new local ID
						if ( isset( $this->processed_posts[$post_parent] ) ) {
							$post_parent = $this->processed_posts[$post_parent];
						// otherwise record the parent for later
						} else {
							$this->post_orphans[intval($post['post_id'])] = $post_parent;
							$post_parent = 0;
						}
					}

					// map the post author
					$author = sanitize_user( $post['post_author'], true );
					if ( isset( $this->author_mapping[$author] ) )
						$author = $this->author_mapping[$author];
					else
						$author = (int) get_current_user_id();

					$postdata = array(
						'import_id' => $post['post_id'], 'post_author' => $author, 'post_date' => $post['post_date'],
						'post_date_gmt' => $post['post_date_gmt'], 'post_content' => $post['post_content'],
						'post_excerpt' => $post['post_excerpt'], 'post_title' => $post['post_title'],
						'post_status' => $post['status'], 'post_name' => $post['post_name'],
						'comment_status' => $post['comment_status'], 'ping_status' => $post['ping_status'],
						'guid' => $post['guid'], 'post_parent' => $post_parent, 'menu_order' => $post['menu_order'],
						'post_type' => $post['post_type'], 'post_password' => $post['post_password']
					);

					$original_post_ID = $post['post_id'];
					$postdata = apply_filters( 'wp_import_post_data_processed', $postdata, $post );

					$postdata = wp_slash( $postdata );

					if ( 'attachment' == $postdata['post_type'] ) {
						$remote_url = ! empty($post['attachment_url']) ? $post['attachment_url'] : $post['guid'];

						// try to use _wp_attached file for upload folder placement to ensure the same location as the export site
						// e.g. location is 2003/05/image.jpg but the attachment post_date is 2010/09, see media_handle_upload()
						$postdata['upload_date'] = $post['post_date'];
						if ( isset( $post['postmeta'] ) ) {
							foreach( $post['postmeta'] as $meta ) {
								if ( $meta['key'] == '_wp_attached_file' ) {
									if ( preg_match( '%^[0-9]{4}/[0-9]{2}%', $meta['value'], $matches ) )
										$postdata['upload_date'] = $matches[0];
									break;
								}
							}
						}

						$comment_post_ID = $post_id = $this->process_attachment( $postdata, $remote_url );
					} else {

						// Replace links.
						$postdata[ 'post_content' ] = Codevz_Demo_Importer::replace_demo_link( $postdata[ 'post_content' ] );

						if ( $postdata[ 'post_type' ] === 'wpcf7_contact_form' ) {

							$domain = wp_parse_url( get_site_url() );
							$domain = $domain['host'];
							$postdata[ 'post_content' ] = str_replace( [ '@xtratheme.com', '@codevz.com', '@themetor.com', '@weebtheme.com', '@gmail.com', '@yahoo.com' ], '@' . $domain, $postdata[ 'post_content' ] );
							$postdata[ 'post_content' ] = str_replace( [ 'xtratheme.com', 'codevz.com', 'themetor.com', 'weebtheme.com' ], $domain, $postdata[ 'post_content' ] );

						}

						$comment_post_ID = $post_id = wp_insert_post( $postdata, true );
						do_action( 'wp_import_insert_post', $post_id, $original_post_ID, $postdata, $post );

						// Save imported.
						Codevz_Demo_Importer::save( 'posts', [ 'id' => $post_id, 'title' => sanitize_title_with_dashes( $post['post_title'] ) ] );

					}

					if ( is_wp_error( $post_id ) ) {
						/* translators: 1. post type name 2. post title */
						printf( 'Failed to import %1$s &#8220;%2$s&#8221;',
							esc_html( $post_type_object->labels->singular_name ), esc_html($post['post_title']) );
						if ( defined('IMPORT_DEBUG') && IMPORT_DEBUG )
							echo ': ' . esc_html( $post_id->get_error_message() );
						echo '<br />';
						continue;
					}

					if ( $post['is_sticky'] == 1 ) {
						stick_post( $post_id );
					}
				//}

				// map pre-import ID to local ID
				$this->processed_posts[intval($post['post_id'])] = (int) $post_id;

				if ( ! isset( $post['terms'] ) )
					$post['terms'] = array();

				$post['terms'] = apply_filters( 'wp_import_post_terms', $post['terms'], $post_id, $post );

				// add categories, tags and other terms
				if ( ! empty( $post['terms'] ) ) {
					$terms_to_set = array();
					foreach ( $post['terms'] as $term ) {
						// back compat with WXR 1.0 map 'tag' to 'post_tag'
						$taxonomy = ( 'tag' == $term['domain'] ) ? 'post_tag' : $term['domain'];
						$term_exists = term_exists( $term['slug'], $taxonomy );
						$term_id = is_array( $term_exists ) ? $term_exists['term_id'] : $term_exists;
						if ( ! $term_id ) {
							$t = wp_insert_term( $term['name'], $taxonomy, array( 'slug' => $term['slug'] ) );
							if ( ! is_wp_error( $t ) ) {
								$term_id = $t['term_id'];
								do_action( 'wp_import_insert_term', $t, $term, $post_id, $post );

								// Save imported.
								Codevz_Demo_Importer::save( 'terms', [ 'id' => $term_id, 'taxonomy' => $taxonomy ] );

							} else {
								/* translators: 1. taxonomy 2. term name */
								printf( 'Failed to import %1$s %2$s', esc_html($taxonomy), esc_html($term['name']) );
								if ( defined('IMPORT_DEBUG') && IMPORT_DEBUG )
									echo ': ' . esc_html( $t->get_error_message() );
								echo '<br />';
								do_action( 'wp_import_insert_term_failed', $t, $term, $post_id, $post );
								continue;
							}
						}
						$terms_to_set[$taxonomy][] = intval( $term_id );
					}

					foreach ( $terms_to_set as $tax => $ids ) {
						$tt_ids = wp_set_post_terms( $post_id, $ids, $tax );
						do_action( 'wp_import_set_post_terms', $tt_ids, $ids, $tax, $post_id, $post );
					}
					unset( $post['terms'], $terms_to_set );
				}

				if ( ! isset( $post['comments'] ) )
					$post['comments'] = array();

				$post['comments'] = apply_filters( 'wp_import_post_comments', $post['comments'], $post_id, $post );

				// add/update comments
				if ( ! empty( $post['comments_SKIP_CODEVZ_IMPORTER'] ) ) {
					$num_comments = 0;
					$inserted_comments = array();
					foreach ( $post['comments'] as $comment ) {
						$comment_id	= $comment['comment_id'];
						$newcomments[$comment_id]['comment_post_ID']      = $comment_post_ID;
						$newcomments[$comment_id]['comment_author']       = $comment['comment_author'];
						$newcomments[$comment_id]['comment_author_email'] = $comment['comment_author_email'];
						$newcomments[$comment_id]['comment_author_IP']    = $comment['comment_author_IP'];
						$newcomments[$comment_id]['comment_author_url']   = $comment['comment_author_url'];
						$newcomments[$comment_id]['comment_date']         = $comment['comment_date'];
						$newcomments[$comment_id]['comment_date_gmt']     = $comment['comment_date_gmt'];
						$newcomments[$comment_id]['comment_content']      = $comment['comment_content'];
						$newcomments[$comment_id]['comment_approved']     = $comment['comment_approved'];
						$newcomments[$comment_id]['comment_type']         = $comment['comment_type'];
						$newcomments[$comment_id]['comment_parent'] 	  = $comment['comment_parent'];
						$newcomments[$comment_id]['commentmeta']          = isset( $comment['commentmeta'] ) ? $comment['commentmeta'] : array();
						if ( isset( $this->processed_authors[$comment['comment_user_id']] ) )
							$newcomments[$comment_id]['user_id'] = $this->processed_authors[$comment['comment_user_id']];
					}
					ksort( $newcomments );

					foreach ( $newcomments as $key => $comment ) {
						// if this is a new post we can skip the comment_exists() check
						if ( ! $post_exists || ! comment_exists( $comment['comment_author'], $comment['comment_date'] ) ) {
							if ( isset( $inserted_comments[$comment['comment_parent']] ) )
								$comment['comment_parent'] = $inserted_comments[$comment['comment_parent']];
							$comment = wp_slash( $comment );
							$comment = wp_filter_comment( $comment );
							$inserted_comments[$key] = wp_insert_comment( $comment );
							do_action( 'wp_import_insert_comment', $inserted_comments[$key], $comment, $comment_post_ID, $post );

							// Save imported.
							Codevz_Demo_Importer::save( 'comments', [ 'id' => $inserted_comments[$key], 'title' => $comment['comment_date'] ] );

							foreach( $comment['commentmeta'] as $meta ) {
								$value = maybe_unserialize( $meta['value'] );
								add_comment_meta( $inserted_comments[$key], $meta['key'], $value );
							}

							$num_comments++;
						}
					}
					unset( $newcomments, $inserted_comments, $post['comments'] );
				}

				if ( ! isset( $post['postmeta'] ) )
					$post['postmeta'] = array();

				$post['postmeta'] = apply_filters( 'wp_import_post_meta', $post['postmeta'], $post_id, $post );

				// add/update post meta
				if ( ! empty( $post['postmeta'] ) ) {
					foreach ( $post['postmeta'] as $meta ) {
						$key = apply_filters( 'import_post_meta_key', $meta['key'], $post_id, $post );
						$value = false;

						if ( '_edit_last' == $key ) {
							if ( isset( $this->processed_authors[intval($meta['value'])] ) )
								$value = $this->processed_authors[intval($meta['value'])];
							else
								$key = false;
						}

						if ( $key ) {

							// export gets meta straight from the DB so could have a serialized string
							if ( ! $value ) {
								$value = maybe_unserialize( $meta['value'] );
							}

							// CODEVZ: Elementor replace URL's.
							if ( $key === '_elementor_data' || $key === 'codevz_page_meta' || $key === 'codevz_single_page_css' || $key === 'cz_sc_styles' ) {

								$is_array = is_array( $value );

								if ( $is_array ) {

									$value = wp_json_encode( $value );

									$value = Codevz_Demo_Importer::replace_upload_url( $value, true );

								} else {

									$value = Codevz_Demo_Importer::replace_upload_url( $value, false );

								}

								$value = Codevz_Demo_Importer::replace_demo_link( $value );

								$value = Codevz_Demo_Importer::replace_demo_link( $value, ( $key === '_elementor_data' || $is_array ) );

								if ( $is_array ) {
									$value = json_decode( $value, true );
								}

								if ( $key === '_elementor_data' ) {
									$value = str_replace( '"normal":""', '"normal":"0"', $value );
									$value = str_replace( '"hover":""', '"hover":"0"', $value );
								}

							}

							add_post_meta( $post_id, wp_slash( $key ), wp_slash_strings_only( $value ) );
							do_action( 'import_post_meta', $post_id, $key, $value );

							// if the post has a featured image, take note of this in case of remap
							if ( '_thumbnail_id' == $key )
								$this->featured_images[$post_id] = (int) $value;
						}
					}
				}

				if ( $i === 4 ) {
					break;
				}
				$i++;
			}

			unset( $this->posts );
			return $j;
		}

		/**
		 * Attempt to create a new menu item from import data
		 *
		 * Fails for draft, orphaned menu items and those without an associated nav_menu
		 * or an invalid nav_menu term. If the post type or term object which the menu item
		 * represents doesn't exist then the menu item will not be imported (waits until the
		 * end of the import to retry again before discarding).
		 *
		 * @param array $item Menu item details from WXR file
		 */
		function process_menu_item( $item ) {
			// skip draft, orphaned menu items
			if ( 'draft' == $item['status'] ) {
				return;
			}

			$menu_slug = false;
			if ( isset($item['terms']) ) {
				// loop through terms, assume first nav_menu term is correct menu
				foreach ( $item['terms'] as $term ) {
					if ( 'nav_menu' == $term['domain'] ) {
						$menu_slug = $term['slug'];
						break;
					}
				}
			}

			// no nav_menu term associated with this menu item
			if ( ! $menu_slug ) {
				esc_html_e( 'Menu item skipped due to missing menu slug', 'codevz-plus' );
				echo '<br />';
				return;
			}

			$menu_id = term_exists( $menu_slug, 'nav_menu' );
			if ( ! $menu_id ) {
				/* translators: 1. menu slug */
				printf( 'Menu item skipped due to invalid menu slug: %s', esc_html( $menu_slug ) );
				echo '<br />';
				return;
			} else {
				$menu_id = is_array( $menu_id ) ? $menu_id['term_id'] : $menu_id;
			}

			// Menu meta.
			foreach( $item['postmeta'] as $meta ) {
				${$meta['key']} = $meta['value'];
			}

			// Codevz Fix.
			if ( empty( $_menu_item_object_id ) ) {
				$_menu_item_object_id = '';
			}
			if ( empty( $_menu_item_object ) ) {
				$_menu_item_object = '';
			}
			if ( empty( $_menu_item_menu_item_parent ) ) {
				$_menu_item_menu_item_parent = '';
			}
			if ( empty( $_menu_item_type ) ) {
				$_menu_item_type = '';
			}
			if ( empty( $_menu_item_url ) ) {
				$_menu_item_url = '';
			}
			if ( empty( $_menu_item_target ) ) {
				$_menu_item_target = '';
			}
			if ( empty( $_menu_item_classes ) ) {
				$_menu_item_classes = '';
			}
			if ( empty( $_menu_item_xfn ) ) {
				$_menu_item_xfn = '';
			}

			if ( 'taxonomy' == $_menu_item_type && isset( $this->processed_terms[intval($_menu_item_object_id)] ) ) {
				$_menu_item_object_id = $this->processed_terms[intval($_menu_item_object_id)];
			} else if ( 'post_type' == $_menu_item_type && isset( $this->processed_posts[intval($_menu_item_object_id)] ) ) {
				$_menu_item_object_id = $this->processed_posts[intval($_menu_item_object_id)];
			} else if ( 'custom' != $_menu_item_type ) {
				// associated object is missing or not imported yet, we'll retry later
				//$this->missing_menu_items[] = $item;
				//return;
			}

			if ( isset( $this->processed_menu_items[intval($_menu_item_menu_item_parent)] ) ) {
				$_menu_item_menu_item_parent = $this->processed_menu_items[intval($_menu_item_menu_item_parent)];
			} else if ( $_menu_item_menu_item_parent ) {
				$this->menu_item_orphans[intval($item['post_id'])] = (int) $_menu_item_menu_item_parent;
				$_menu_item_menu_item_parent = 0;
			}

			// wp_update_nav_menu_item expects CSS classes as a space separated string
			$_menu_item_classes = maybe_unserialize( $_menu_item_classes );
			if ( is_array( $_menu_item_classes ) ) {
				$_menu_item_classes = implode( ' ', $_menu_item_classes );
			}

			if ( $_menu_item_url ) {
				$_menu_item_url = Codevz_Demo_Importer::replace_demo_link( $_menu_item_url, true );
				$_menu_item_url = Codevz_Demo_Importer::replace_demo_link( $_menu_item_url );
			}

			$args = array(
				'menu-item-object-id' => $_menu_item_object_id,
				'menu-item-object' => $_menu_item_object,
				'menu-item-parent-id' => $_menu_item_menu_item_parent,
				'menu-item-position' => intval( $item['menu_order'] ),
				'menu-item-type' => $_menu_item_type,
				'menu-item-title' => $item['post_title'],
				'menu-item-url' => $_menu_item_url, 
				'menu-item-description' => $item['post_content'],
				'menu-item-attr-title' => $item['post_excerpt'],
				'menu-item-target' => $_menu_item_target,
				'menu-item-classes' => $_menu_item_classes,
				'menu-item-xfn' => $_menu_item_xfn,
				'menu-item-status' => $item['status']
			);

			$id = wp_update_nav_menu_item( $menu_id, 0, $args );

			if ( $id && ! is_wp_error( $id ) ) {
				$this->processed_menu_items[ intval( $item['post_id'] ) ] = (int) $id;
			}

		}

		/**
		 * If fetching attachments is enabled then attempt to create a new attachment
		 *
		 * @param array $post Attachment post details from WXR
		 * @param string $url URL to fetch attachment from
		 * @return int|WP_Error Post ID on success, WP_Error otherwise
		 */
		function process_attachment( $post, $url ) {
			if ( ! $this->fetch_attachments )
				return new WP_Error( 'attachment_processing_error', 'Fetching attachments is not enabled' );

			// if the URL is absolute, but does not contain address, then upload it assuming base_site_url
			if ( preg_match( '|^/[\w\W]+$|', $url ) )
				$url = rtrim( $this->base_url, '/' ) . $url;

			$upload = $this->fetch_remote_file( $url, $post );
			if ( is_wp_error( $upload ) )
				return $upload;

			if ( $info = wp_check_filetype( $upload['file'] ) )
				$post['post_mime_type'] = $info['type'];
			else
				return new WP_Error( 'attachment_processing_error', 'Invalid file type' );

			$post['guid'] = $upload['url'];

			// as per wp-admin/includes/upload.php
			$post_id = wp_insert_attachment( $post, $upload['file'] );
			wp_update_attachment_metadata( $post_id, wp_generate_attachment_metadata( $post_id, $upload['file'] ) );

			// remap resized image URLs, works by stripping the extension and remapping the URL stub.
			if ( preg_match( '!^image/!', $info['type'] ) ) {
				$parts = pathinfo( $url );
				$name = basename( $parts['basename'], ".{$parts['extension']}" ); // PATHINFO_FILENAME in PHP 5.2

				$parts_new = pathinfo( $upload['url'] );
				$name_new = basename( $parts_new['basename'], ".{$parts_new['extension']}" );

				$this->url_remap[$parts['dirname'] . '/' . $name] = $parts_new['dirname'] . '/' . $name_new;
			}

			return $post_id;
		}

		/**
		 * Attempt to download a remote file attachment
		 *
		 * @param string $url URL of item to fetch
		 * @param array $post Attachment details
		 * @return array|WP_Error Local file location details on success, WP_Error otherwise
		 */
		function fetch_remote_file( $url, $post ) {
			// extract the file name and extension from the url
			$file_name = basename( $url );

			// get placeholder file in the upload dir with a unique, sanitized filename
			$upload = wp_upload_bits( $file_name, null, '', $post['upload_date'] );
			if ( $upload['error'] )
				return new WP_Error( 'upload_dir_error', $upload['error'] );

			// fetch the remote url and write it to the placeholder file
			$remote_response = wp_safe_remote_get( $url, array(
				'timeout' => 300,
	            		'stream' => true,
	            		'filename' => $upload['file'],
	        	) );

			$headers = wp_remote_retrieve_headers( $remote_response );

			// request failed
			if ( ! $headers ) {
				wp_delete_file( $upload['file'] );
				return new WP_Error( 'import_file_error', 'Remote server did not respond' );
			}

			$remote_response_code = wp_remote_retrieve_response_code( $remote_response );

			// make sure the fetch was successful
			if ( $remote_response_code != '200' ) {
				wp_delete_file( $upload['file'] );
				/* translators: 1. response 2. status */
				return new WP_Error( 'import_file_error', sprintf( 'Remote server returned error response %1$d %2$s', esc_html($remote_response_code), get_status_header_desc($remote_response_code) ) );
			}

			$filesize = filesize( $upload['file'] );

			if ( isset( $headers['content-length'] ) && $filesize != $headers['content-length'] ) {
				wp_delete_file( $upload['file'] );
				return new WP_Error( 'import_file_error', 'Remote file is incorrect size' );
			}

			if ( 0 == $filesize ) {
				wp_delete_file( $upload['file'] );
				return new WP_Error( 'import_file_error', 'Zero size file downloaded' );
			}

			$max_size = (int) $this->max_attachment_size();
			if ( ! empty( $max_size ) && $filesize > $max_size ) {
				wp_delete_file( $upload['file'] );
				/* translators: 1. max size limit */
				return new WP_Error( 'import_file_error', sprintf('Remote file is too large, limit is %s', size_format($max_size) ) );
			}

			// keep track of the old and new urls so we can substitute them later
			$this->url_remap[$url] = $upload['url'];
			$this->url_remap[$post['guid']] = $upload['url']; // r13735, really needed?
			// keep track of the destination if the remote url is redirected somewhere else
			if ( isset($headers['x-final-location']) && $headers['x-final-location'] != $url )
				$this->url_remap[$headers['x-final-location']] = $upload['url'];

			return $upload;
		}

		/**
		 * Attempt to associate posts and menu items with previously missing parents
		 *
		 * An imported post's parent may not have been imported when it was first created
		 * so try again. Similarly for child menu items and menu items which were missing
		 * the object (e.g. post) they represent in the menu
		 */
		function backfill_parents() {

			$db = Codevz_Plus::database();

			// find parents for post orphans
			foreach ( $this->post_orphans as $child_id => $parent_id ) {
				$local_child_id = $local_parent_id = false;
				if ( isset( $this->processed_posts[$child_id] ) )
					$local_child_id = $this->processed_posts[$child_id];
				if ( isset( $this->processed_posts[$parent_id] ) )
					$local_parent_id = $this->processed_posts[$parent_id];

				if ( $local_child_id && $local_parent_id ) {
					$db->update( $db->posts, array( 'post_parent' => $local_parent_id ), array( 'ID' => $local_child_id ), '%d', '%d' );
					clean_post_cache( $local_child_id );
				}
			}

			// all other posts/terms are imported, retry menu items with missing associated object
			$missing_menu_items = $this->missing_menu_items;
			foreach ( $missing_menu_items as $item )
				$this->process_menu_item( $item );

			// find parents for menu item orphans
			foreach ( $this->menu_item_orphans as $child_id => $parent_id ) {
				$local_child_id = $local_parent_id = 0;
				if ( isset( $this->processed_menu_items[$child_id] ) )
					$local_child_id = $this->processed_menu_items[$child_id];
				if ( isset( $this->processed_menu_items[$parent_id] ) )
					$local_parent_id = $this->processed_menu_items[$parent_id];

				if ( $local_child_id && $local_parent_id )
					update_post_meta( $local_child_id, '_menu_item_menu_item_parent', (int) $local_parent_id );
			}
		}

		/**
		 * Use stored mapping information to update old attachment URLs
		 */
		function backfill_attachment_urls() {

			$db = Codevz_Plus::database();

			// make sure we do the longest urls first, in case one is a substring of another
			uksort( $this->url_remap, array(&$this, 'cmpr_strlen') );

			foreach ( $this->url_remap as $from_url => $to_url ) {
				// remap urls in post_content
				$db->query( $db->prepare("UPDATE {$db->posts} SET post_content = REPLACE(post_content, %s, %s)", $from_url, $to_url) );
				// remap enclosure urls
				$result = $db->query( $db->prepare("UPDATE {$db->postmeta} SET meta_value = REPLACE(meta_value, %s, %s) WHERE meta_key='enclosure'", $from_url, $to_url) );
			}
		}

		/**
		 * Update _thumbnail_id meta to new, imported attachment IDs
		 */
		function remap_featured_images() {
			// cycle through posts that have a featured image
			foreach ( $this->featured_images as $post_id => $value ) {
				if ( isset( $this->processed_posts[$value] ) ) {
					$new_id = $this->processed_posts[$value];
					// only update if there's a difference
					if ( $new_id != $value )
						update_post_meta( $post_id, '_thumbnail_id', $new_id );
				}
			}
		}

		/**
		 * Parse a WXR file
		 *
		 * @param string $file Path to WXR file for parsing
		 * @return array Information gathered from the WXR file
		 */
		function parse( $file ) {
			$parser = new WXR_Parser();
			return $parser->parse( $file );
		}

		// Display import page title
		function header() {
			echo '<div class="wrap">';
			echo '<h2>Import WordPress</h2>';

			$updates = get_plugin_updates();
			$basename = plugin_basename(__FILE__);
			if ( isset( $updates[$basename] ) ) {
				$update = $updates[$basename];
				echo '<div class="error"><p><strong>';
				/* translators: 1. update version */
				printf( 'A new version of this importer is available. Please update to version %s to ensure compatibility with newer export files.', esc_html( $update->update->new_version ) );
				echo '</strong></p></div>';
			}
		}

		// Close div.wrap
		function footer() {
			echo '</div>';
		}

		/**
		 * Display introductory text and file upload form
		 */
		function greet() {
			echo '<div class="narrow">';
			echo '<p>Howdy! Upload your WordPress eXtended RSS (WXR) file and we&#8217;ll import the posts, pages, comments, custom fields, categories, and tags into this site.</p>';
			echo '<p>Choose a WXR (.xml) file to upload, then click Upload file and import.</p>';
			wp_import_upload_form( 'admin.php?import=wordpress&amp;step=1' );
			echo '</div>';
		}

		/**
		 * Decide if the given meta key maps to information we will want to import
		 *
		 * @param string $key The meta key to check
		 * @return string|bool The key if we do want to import, false if not
		 */
		function is_valid_meta_key( $key ) {
			// skip attachment metadata since we'll regenerate it from scratch
			// skip _edit_lock as not relevant for import
			if ( in_array( $key, array( '_wp_attached_file', '_wp_attachment_metadata', '_edit_lock' ) ) )
				return false;
			return $key;
		}

		/**
		 * Decide whether or not the importer is allowed to create users.
		 * Default is true, can be filtered via import_allow_create_users
		 *
		 * @return bool True if creating users is allowed
		 */
		function allow_create_users() {
			return apply_filters( 'import_allow_create_users', true );
		}

		/**
		 * Decide whether or not the importer should attempt to download attachment files.
		 * Default is true, can be filtered via import_allow_fetch_attachments. The choice
		 * made at the import options screen must also be true, false here hides that checkbox.
		 *
		 * @return bool True if downloading attachments is allowed
		 */
		function allow_fetch_attachments() {
			return apply_filters( 'import_allow_fetch_attachments', true );
		}

		/**
		 * Decide what the maximum file size for downloaded attachments is.
		 * Default is 0 (unlimited), can be filtered via import_attachment_size_limit
		 *
		 * @return int Maximum attachment file size to import
		 */
		function max_attachment_size() {
			return apply_filters( 'import_attachment_size_limit', 0 );
		}

		/**
		 * Added to http_request_timeout filter to force timeout at 60 seconds during import
		 * @return int 60
		 */
		function bump_request_timeout( $val ) {
			return 60;
		}

		// return the difference in length between two strings
		function cmpr_strlen( $a, $b ) {
			return strlen($b) - strlen($a);
		}
	}

}