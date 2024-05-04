<?php
/**
 * Helper class.
 *
 * @package DOWP
 */

namespace DOWP\AiContentGenerate\Helpers;

use DOWP\AiContentGenerate\Models\Field;
use DOWP\AiContentGenerate\Models\ReSizer;

// Do not allow directly accessing this file.
if ( ! defined( 'ABSPATH' ) ) {
	exit( 'This script cannot be accessed directly.' );
}

/**
 * Helper class.
 */
class Fns {

	/**
	 * Get Ajax URL.
	 *
	 * @return string
	 */
	public function ajax_url() {
		return admin_url( 'admin-ajax.php', 'relative' );
	}


	/**
	 * Render view
	 *
	 * @param string $viewName View name.
	 * @param array $args Args.
	 * @param boolean $return Include/return.
	 *
	 * @return string
	 */
	public static function view( $viewName, $args = [], $return = false ) {
		$file     = str_replace( '.', '/', $viewName );
		$file     = ltrim( $file, '/' );
		$viewFile = trailingslashit( AI_CONTENT_PLUGIN_PATH . '/resources' ) . $file . '.php';

		if ( ! file_exists( $viewFile ) ) {
			return new \WP_Error(
				'brock',
				sprintf(
				/* translators: %s File name */
					esc_html__( '%s file not found', 'ai-content-generate' ),
					$viewFile
				)
			);
		}

		if ( $args ) {
			extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		if ( $return ) {
			ob_start();
			include $viewFile;

			return ob_get_clean();
		}

		include $viewFile;
	}

	/**
	 * Update post view
	 *
	 * @param integer $post_id Listing ID.
	 *
	 * @return void
	 */
	public static function update_post_views_count( $post_id ) {

		if ( ! $post_id && is_admin() ) {
			return;
		}

		$user_ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ); // retrieve the current IP address of the visitor.
		$key     = 'tpg_cache_' . $user_ip . '_' . $post_id;
		$value   = [ $user_ip, $post_id ];
		$visited = get_transient( $key );


		if ( false === ( $visited ) ) {
			//set_transient( $key, $value, HOUR_IN_SECONDS * 12 ); // store the unique key, Post ID & IP address for 12 hours if it does not exist.
			set_transient( $key, $value, HOUR_IN_SECONDS * 12 ); // store the unique key, Post ID & IP address for 12 hours if it does not exist.

			// now run post views function.
			$count_key = self::get_post_view_count_meta_key();
			$count     = get_post_meta( $post_id, $count_key, true );

			if ( '' == $count ) {
				update_post_meta( $post_id, $count_key, 1 );
			} else {
				$count = absint( $count );
				$count ++;

				update_post_meta( $post_id, $count_key, $count );
			}
		}
	}

	/**
	 * Template Content
	 *
	 * @param string $template_name Template name.
	 * @param array $args Arguments. (default: array).
	 * @param string $template_path Template path. (default: '').
	 * @param string $default_path Default path. (default: '').
	 */
	public static function get_template( $template_name, $args = null, $template_path = '', $default_path = '' ) {
		if ( ! empty( $args ) && is_array( $args ) ) {
			extract( $args ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		}

		$located = self::locate_template( $template_name, $template_path, $default_path );

		if ( ! file_exists( $located ) ) {
			/* translators: %s template */
			self::doing_it_wrong( __FUNCTION__, sprintf( esc_html__( '%s does not exist.', 'ai-content-generate' ), '<code>' . $located . '</code>' ), '1.0' );

			return;
		}

		// Allow 3rd party plugin filter template file from their plugin.
		$located = apply_filters( 'dowp_get_template', $located, $template_name, $args );

		do_action( 'dowp_before_template_part', $template_name, $located, $args );

		include $located;

		do_action( 'dowp_after_template_part', $template_name, $located, $args );
	}

	/**
	 * Get template content and return
	 *
	 * @param string $template_name Template name.
	 * @param array $args Arguments. (default: array).
	 * @param string $template_path Template path. (default: '').
	 * @param string $default_path Default path. (default: '').
	 *
	 * @return string
	 */
	public static function get_template_html( $template_name, $args = [], $template_path = '', $default_path = '' ) {
		ob_start();
		self::get_template( $template_name, $args, $template_path, $default_path );

		return ob_get_clean();
	}

	/**
	 * Locate template.
	 *
	 * @param string $template_name Template.
	 * @param string $template_path Path.
	 * @param string $default_path Default path.
	 *
	 * @return mixed|void
	 */
	public static function locate_template( $template_name, $template_path = '', $default_path = '' ) {
		$template_name = $template_name . '.php';

		if ( ! $template_path ) {
			$template_path = dwpAIC()->get_template_path();
		}

		if ( ! $default_path ) {
			$default_path = dwpAIC()->default_template_path() . '/templates/';
		}

		// Look within passed path within the theme - this is priority.
		$template_files   = [];
		$template_files[] = trailingslashit( $template_path ) . $template_name;

		$template = locate_template( apply_filters( 'dowp_locate_template_files', $template_files, $template_name, $template_path, $default_path ) );

		// Get default template/.
		if ( ! $template ) {
			$template = trailingslashit( $default_path ) . $template_name;
		}

		return apply_filters( 'dowp_locate_template', $template, $template_name );
	}

	/**
	 * Mark something as being incorrectly called.
	 *
	 * @param string $function — The function that was called.
	 * @param string $message — A message explaining what has been done incorrectly.
	 * @param string $version — The version of WordPress where the message was added.
	 *
	 * @return void
	 */
	public static function doing_it_wrong( $function, $message, $version ) {
		// phpcs:disable
		$message .= ' Backtrace: ' . wp_debug_backtrace_summary();
		_doing_it_wrong( $function, $message, $version );
		// phpcs:enable
	}

	/**
	 * Verify nonce.
	 *
	 * @return bool
	 */
	public static function verifyNonce() {
		$nonce     = isset( $_REQUEST[ dwpAIC()->nonceId() ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ dwpAIC()->nonceId() ] ) ) : null;
		$nonceText = dwpAIC()->nonceText();

		if ( ! wp_verify_nonce( $nonce, $nonceText ) ) {
			return false;
		}

		return true;
	}

	/**
	 * @param $data
	 * @param $temp_path
	 *
	 * @return void
	 */
	public static function tpg_template( $data, $temp_path = 'elementor' ) {
		$layout = str_replace( '-2', '', $data['layout'] );

		$template_name = '/ai-content-generate/' . $temp_path . '/' . $layout . '.php';
		if ( file_exists( STYLESHEETPATH . $template_name ) ) {
			$file = STYLESHEETPATH . $template_name;
		} else if ( file_exists( TEMPLATEPATH . $template_name ) ) {
			$file = TEMPLATEPATH . $template_name;
		} else {
			$file = AI_CONTENT_PLUGIN_PATH . '/templates/' . $temp_path . '/' . $layout . '.php';
			if ( ! file_exists( $file ) ) {
				if ( dwpAIC()->hasPro() ) {
					$file = AI_CONTENT_PRO_PLUGIN_PATH . '/templates/' . $temp_path . '/' . $layout . '.php';
				} else {
					$layout = substr( $layout, 0, - 1 );
					$layout = strpos( $layout, '1' ) ? str_replace( '1', '', $layout ) : $layout;
					$file   = AI_CONTENT_PLUGIN_PATH . '/templates/' . $temp_path . '/' . $layout . '1.php';
				}
			}
		}

		ob_start();
		include $file;
		echo ob_get_clean();
	}

	/**
	 * @param $data
	 *
	 * @return string
	 */
	public static function tpg_template_path( $data, $temp_path = 'elementor' ) {
		$layout        = str_replace( '-2', '', $data['layout'] );
		$template_name = '/ai-content-generate/' . $temp_path . '/' . $layout . '.php';
		$path          = AI_CONTENT_PLUGIN_PATH . '/templates/' . $temp_path . '/';
		if ( file_exists( STYLESHEETPATH . $template_name ) ) {
			$path = STYLESHEETPATH . '/ai-content-generate/' . $temp_path . '/';
		} else if ( file_exists( TEMPLATEPATH . $template_name ) ) {
			$path = TEMPLATEPATH . '/ai-content-generate/' . $temp_path . '/';
		} else {
			$template_path = AI_CONTENT_PLUGIN_PATH . '/templates/' . $temp_path . '/' . $layout . '.php';

			if ( ! file_exists( $template_path ) && dwpAIC()->hasPro() ) {
				$path = AI_CONTENT_PRO_PLUGIN_PATH . '/templates/' . $temp_path . '/';
			}
		}

		return $path;
	}

	/**
	 * Get Post Pagination, Load more & Scroll markup
	 *
	 * @param $query
	 * @param $data
	 *
	 * @return false|string|void
	 */
	public static function get_pagination_markup( $query, $data ) {
		if ( 'show' !== $data['show_pagination'] ) {
			return;
		}

		$htmlUtility = null;

		$posts_loading_type = $data['pagination_type'];

		$posts_per_page = ( isset( $data['display_per_page'] ) && $data['display_per_page'] ) ? $data['display_per_page'] : ( $data['post_limit'] ?? get_option( 'posts_per_page' ) );

		if ( ! empty( $data['is_builder'] ) && $data['is_builder'] === 'yes' ) {
			$posts_per_page = get_option( 'posts_per_page' );
		}

		$hide = ( $query->max_num_pages < 2 ? " rt-hidden-elm" : null );

		if ( $posts_loading_type == "pagination" ) {
			$htmlUtility .= Fns::rt_pagination( $query );
		} else if ( dwpAIC()->hasPro() && $posts_loading_type == "pagination_ajax" ) { //&& ! $isIsotope
			$htmlUtility .= "<div class='rt-page-numbers'></div>";
		} else if ( dwpAIC()->hasPro() && $posts_loading_type == "load_more" ) {
			$load_more_button_text = $data['load_more_button_text'] ? $data['load_more_button_text'] : __( "Load More", 'ai-content-generate' );
			$htmlUtility           .= "<div class='rt-loadmore-btn rt-loadmore-action rt-loadmore-style{$hide}'>
											<span class='rt-loadmore-text'>" . $load_more_button_text . "</span>
											<div class='rt-loadmore-loading rt-ball-scale-multiple rt-2x'><div></div><div></div><div></div></div>
										</div>";
		} else if ( dwpAIC()->hasPro() && $posts_loading_type == "load_on_scroll" ) {
			$htmlUtility .= "<div class='rt-infinite-action'>	
                                <div class='rt-infinite-loading la-fire la-2x'>
                                    <div></div><div></div><div></div>
                                </div>
                            </div>";
		}

		if ( $htmlUtility ) {
			$html = "<div class='rt-pagination-wrap' data-total-pages='{$query->max_num_pages}' data-posts-per-page='{$posts_per_page}' data-type='{$posts_loading_type}' >"
			        . $htmlUtility . "</div>";

			return $html;
		}

		return false;
	}

	/**
	 * @param $data
	 * @param $total_pages
	 * @param $posts_per_page
	 * @param $_prefix
	 * @param $is_gutenberg
	 *
	 * @return array
	 */
	public static function get_render_data_set( $data, $total_pages, $posts_per_page, $_prefix, $is_gutenberg = '' ) {

		if ( ! empty( $data['is_builder'] ) && $data['is_builder'] === 'yes' ) {
			$posts_per_page = get_option( 'posts_per_page' );
		}

		$data_set = [
			'block_type'                   => 'elementor',
			'is_gutenberg'                 => $is_gutenberg,
			'prefix'                       => $_prefix,
			'grid_column'                  => $data[ $_prefix . '_column' ] ?? '0',
			'grid_column_tablet'           => $data[ $_prefix . '_column_tablet' ] ?? '0',
			'grid_column_mobile'           => $data[ $_prefix . '_column_mobile' ] ?? '0',
			'layout'                       => $data[ $_prefix . '_layout' ],
			'pagination_type'              => 'slider' === $_prefix ? 'slider' : $data['pagination_type'],
			'total_pages'                  => $total_pages,
			'posts_per_page'               => $posts_per_page,
			'layout_style'                 => $data[ $_prefix . '_layout_style' ] ?? '',
			'show_title'                   => $data['show_title'],
			'excerpt_type'                 => $data['excerpt_type'],
			'excerpt_limit'                => $data['excerpt_limit'],
			'excerpt_more_text'            => $data['excerpt_more_text'],
			'title_limit'                  => $data['title_limit'],
			'title_limit_type'             => $data['title_limit_type'],
			'title_visibility_style'       => $data['title_visibility_style'],
			'post_link_type'               => $data['post_link_type'],
			'link_target'                  => $data['link_target'],
			'hover_animation'              => $data['hover_animation'] ?? '',
			'show_thumb'                   => $data['show_thumb'],
			'show_meta'                    => $data['show_meta'],
			'show_author'                  => $data['show_author'],
			'show_author_image'            => $data['show_author_image'],
			'author_icon_visibility'       => $data['author_icon_visibility'],
			'show_meta_icon'               => $data['show_meta_icon'],
			'show_category'                => $data['show_category'],
			'show_date'                    => $data['show_date'],
			'show_tags'                    => $data['show_tags'],
			'show_comment_count'           => $data['show_comment_count'],
			'show_comment_count_label'     => $data['show_comment_count_label'] ?? '',
			'comment_count_label_singular' => $data['comment_count_label_singular'] ?? '',
			'comment_count_label_plural'   => $data['comment_count_label_plural'] ?? '',
			'show_post_count'              => $data['show_post_count'],
			'post_count_icon'              => $data['post_count_icon'] ?? '',
			'show_excerpt'                 => $data['show_excerpt'],
			'show_read_more'               => $data['show_read_more'],
			'show_btn_icon'                => $data['show_btn_icon'],
			'show_social_share'            => $data['show_social_share'],
			'show_cat_icon'                => $data['show_cat_icon'] ?? '',
			'is_thumb_linked'              => $data['is_thumb_linked'],
			'media_source'                 => $data['media_source'],
			'no_posts_found_text'          => isset( $data['no_posts_found_text'] ) ? esc_html( $data['no_posts_found_text'] ) : '',
			'image_size'                   => $data['image_size'],
			'image_offset'                 => $data['image_offset_size'],
			'is_default_img'               => $data['is_default_img'],
			'default_image'                => $data['default_image'],
			'thumb_overlay_visibility'     => $data['thumb_overlay_visibility'] ?? '',
			'overlay_type'                 => $data['overlay_type'] ?? '',
			'title_tag'                    => $data['title_tag'],
			'post_type'                    => $data['post_type'],
			'meta_separator'               => $data['meta_separator'],
			'readmore_icon_position'       => $data['readmore_icon_position'],
			'read_more_label'              => $data['read_more_label'],
			'readmore_btn_icon'            => $data['readmore_btn_icon'],
			'category_position'            => $data['category_position'],
			'title_position'               => $data['title_position'] ?? 'default',
			'category_style'               => $data['category_style'] ?? '',
			'is_thumb_lightbox'            => $data['is_thumb_lightbox'],
			'author_prefix'                => $data['author_prefix'],
			'cat_icon'                     => $data['cat_icon'] ?? '',
			'tag_icon'                     => $data['tag_icon'] ?? '',
			'date_icon'                    => $data['date_icon'] ?? '',
			'user_icon'                    => $data['user_icon'] ?? '',
			'meta_ordering'                => $data['meta_ordering'],
			'comment_icon'                 => $data['comment_icon'] ?? '',
			'image_custom_dimension'       => ( $data['image_size'] == 'custom' && isset( $data['image_custom_dimension'] ) ) ? $data['image_custom_dimension'] : [],
			'img_crop_style'               => ( $data['image_size'] == 'custom' && isset( $data['img_crop_style'] ) ) ? $data['img_crop_style'] : '',
			'show_acf'                     => $data['show_acf'] ?? '',
			'search_by'                    => $data['search_by'] ?? '',
		];

		$cf = Fns::is_acf();
		if ( $cf && dwpAIC()->hasPro() ) {
			$post_type = $data['post_type'];
			if ( $is_gutenberg && isset( $data['acf_data_lists'][ $post_type . '_cf_group' ] ) ) {
				$cf_group             = $data['acf_data_lists'][ $post_type . '_cf_group' ]['options'];
				$data_set['cf_group'] = wp_list_pluck( $cf_group, 'value' );
			} else {
				$data_set['cf_group'] = $data[ $post_type . '_cf_group' ];
			}
			$data_set['cf_hide_empty_value'] = $data['cf_hide_empty_value'];
			$data_set['cf_show_only_value']  = $data['cf_show_only_value'];
			$data_set['cf_hide_group_title'] = $data['cf_hide_group_title'];
		}
		if ( $is_gutenberg ) {
			unset( $data_set['grid_column'] );
			unset( $data_set['grid_column_mobile'] );
			unset( $data_set['grid_column_mobile'] );
			unset( $data_set['layout_style'] );
			$data_set['c_image_width']  = $data['c_image_width'] ?? '';
			$data_set['c_image_height'] = $data['c_image_height'] ?? '';
			$data_set['grid_column']    = (array) $data['grid_column'];
			$data_set['layout_style']   = $data['grid_layout_style'];
		}

		return $data_set;
	}


	/**
	 * Get Filter markup
	 *
	 * @param $data
	 *
	 * @return string
	 */
	public static function get_frontend_filter_markup( $data, $is_guten = false ) {
		if ( ! dwpAIC()->hasPro()
		     || ! ( $data['show_taxonomy_filter'] == 'show' || $data['show_author_filter'] == 'show' || $data['show_order_by'] == 'show'
		            || $data['show_sort_order'] == 'show'
		            || $data['show_search'] == 'show' )
		) {
			return;
		}

		$html             = null;
		$wrapperContainer = $wrapperClass = $itemClass = $filter_btn_item_per_page = '';
		$postCountClass   = null;

		if ( 'carousel' === $data['filter_btn_style'] ) {
			$wrapperContainer  = 'swiper';
			$wrapperClass      = 'swiper-wrapper';
			$itemClass         = 'swiper-slide';
			$filter_btn_mobile = isset( $data['filter_btn_item_per_page_mobile'] ) ? $data['filter_btn_item_per_page_mobile'] : 'auto';
			$filter_btn_tablet = isset( $data['filter_btn_item_per_page_tablet'] ) ? $data['filter_btn_item_per_page_tablet'] : 'auto';
			$filter_btn_item_per_page
			                   = "data-per-page = '{$data['filter_btn_item_per_page']}' data-per-page-mobile = '{$filter_btn_mobile}' data-per-tablet = '{$filter_btn_tablet}'";
		}

		$html .= "<div class='rt-layout-filter-container rt-clear'><div class='rt-filter-wrap'>";

		if ( 'show' == $data['show_author_filter'] || 'show' == $data['show_taxonomy_filter'] ) {
			$html .= "<div class='filter-left-wrapper {$wrapperContainer}' {$filter_btn_item_per_page}>";
		}
		//		if($data['filter_btn_style'] == 'carousel') {
		//		    $html .= "<div class='swiper-pagination'></div>";
		//        }
		$selectedSubTermsForButton = null;

		$filterType = $data['filter_type'];
		$post_count = ( 'yes' == $data['filter_post_count'] ) ? true : false;


		if ( 'show' == $data['show_taxonomy_filter'] ) {
			$postCountClass = ( $post_count ? " has-post-count" : null );
			$allSelect      = " selected";
			$isTermSelected = false;

			$taxFilterOperator = $data['relation'];

			$taxonomy_label = $default_term = '';
			if ( $is_guten ) {
				$taxFilter = $data['filter_taxonomy'];
			} else {
				$section_term_key = $data['post_type'] . '_filter_taxonomy';
				$taxFilter        = $data[ $section_term_key ];
			}

			if ( $taxFilter ) {
				$taxonomy_details = get_taxonomy( $taxFilter );
				$taxonomy_label   = $taxonomy_details->label;
				$default_term_key = $taxFilter . '_default_terms';
				$default_term     = isset( $data[ $default_term_key ] ) ? $data[ $default_term_key ] : '';
			}

			$allText = $data['tax_filter_all_text'] ? $data['tax_filter_all_text'] : __( "All ", "ai-content-generate" ) . $taxonomy_label;


			$_taxonomies = get_object_taxonomies( $data['post_type'], 'objects' );
			$terms       = [];

			foreach ( $_taxonomies as $index => $object ) {
				if ( $object->name != $taxFilter ) {
					continue;
				}
				$setting_key = $object->name . '_ids';

				//Gutenberg
				if ( $is_guten && ! empty( $data['taxonomy_lists'][ $object->name ]['options'] ) ) {
					//This block execute if gutenberg editor has taxonomy query
					$terms = wp_list_pluck( $data['taxonomy_lists'][ $object->name ]['options'], 'value' );
				} //Elementor
				else if ( ! empty( $data[ $setting_key ] ) ) {
					//This block execute for Elementor editor has taxonomy query
					$_terms = $data[ $setting_key ];
					$terms  = get_terms( [
						'taxonomy' => $taxFilter,
						'fields'   => 'ids',
						'include'  => $_terms,
						'orderby'  => 'meta_value_num',
						'meta_key' => '_rt_order',
						'order'    => 'ASC',
					] );
				} //Shortcode
				else {
					//Execute if there is no taxonomy query
					$terms = get_terms( [
						'taxonomy' => $taxFilter,
						'fields'   => 'ids',
						'orderby'  => 'meta_value_num',
						'meta_key' => '_rt_order',
						'order'    => 'ASC',
					] );
				}
			}

			//'orderby'    => 'meta_value_num',
			//'meta_key'   => '_rt_order',
			//'order'      => 'ASC',

			$taxFilterTerms = $terms;

			if ( $default_term && $taxFilter ) {
				$isTermSelected = true;
				$allSelect      = null;
			}

			if ( $filterType == 'dropdown' ) {
				$html             .= "<div class='rt-filter-item-wrap rt-tax-filter rt-filter-dropdown-wrap parent-dropdown-wrap{$postCountClass}' data-taxonomy='{$taxFilter}' data-filter='taxonomy'>";
				$termDefaultText  = $allText;
				$dataTerm         = 'all';
				$htmlButton       = "";
				$selectedSubTerms = null;
				$pCount           = 0;


				if ( ! empty( $terms ) ) {
					$i = 0;
					foreach ( $terms as $term_id ) {
						$term   = get_term( $term_id, $taxFilter, ARRAY_A );
						$id     = $term['term_id'];
						$pCount = $pCount + $term['count'];
						$sT     = null;
						if ( $data['tgp_filter_taxonomy_hierarchical'] == 'yes' ) {
							$subTerms = Fns::rt_get_all_term_by_taxonomy( $taxFilter, true, $id );
							if ( ! empty( $subTerms ) ) {
								$count = 0;
								$item  = $allCount = null;
								foreach ( $subTerms as $stId => $t ) {
									$count       = $count + absint( $t['count'] );
									$sTPostCount = ( $post_count ? " (<span class='rt-post-count'>{$t['count']}</span>)" : null );
									$item        .= "<span class='term-dropdown-item rt-filter-dropdown-item' data-term='{$stId}'><span class='rt-text'>{$t['name']}{$sTPostCount}</span></span>";
								}
								if ( $post_count ) {
									$allCount = " (<span class='rt-post-count'>{$count}</span>)";
								}
								$sT .= "<div class='rt-filter-item-wrap rt-tax-filter rt-filter-dropdown-wrap sub-dropdown-wrap{$postCountClass}'>";
								$sT .= "<span class='term-default rt-filter-dropdown-default' data-term='{$id}'>
								                        <span class='rt-text'>" . $allText . "</span>
								                        <i class='fa fa-angle-down rt-arrow-angle' aria-hidden='true'></i>
								                    </span>";
								$sT .= '<span class="term-dropdown rt-filter-dropdown">';
								$sT .= $item;
								$sT .= '</span>';
								$sT .= "</div>";
							}
							if ( $default_term === $id ) {
								$selectedSubTerms = $sT;
							}
						}
						$postCount = ( $post_count ? " (<span class='rt-post-count'>{$term['count']}</span>)" : null );
						if ( $default_term && $default_term == $id ) {
							$termDefaultText = $term['name'] . $postCount;
							$dataTerm        = $id;
						}
						if ( is_array( $taxFilterTerms ) && ! empty( $taxFilterTerms ) ) {
							if ( in_array( $id, $taxFilterTerms ) ) {
								$htmlButton .= "<span class='term-dropdown-item rt-filter-dropdown-item' data-term='{$id}'><span class='rt-text'>{$term['name']}{$postCount}</span>{$sT}</span>";
							}
						} else {
							$htmlButton .= "<span class='term-dropdown-item rt-filter-dropdown-item' data-term='{$id}'><span class='rt-text'>{$term['name']}{$postCount}</span>{$sT}</span>";
						}
						$i ++;
					}
				}
				$pAllCount = null;
				if ( $post_count ) {
					$pAllCount = " (<span class='rt-post-count'>{$pCount}</span>)";
					if ( ! $default_term ) {
						$termDefaultText = $termDefaultText;
					}
				}

				if ( 'yes' == $data['tpg_hide_all_button'] ) {
					$htmlButton = "<span class='term-dropdown-item rt-filter-dropdown-item' data-term='all'><span class='rt-text'>" . $allText . "</span></span>"
					              . $htmlButton;
				}
				$htmlButton = sprintf( '<span class="term-dropdown rt-filter-dropdown">%s</span>', $htmlButton );

				$showAllhtml = '<span class="term-default rt-filter-dropdown-default" data-term="' . $dataTerm . '">
								                        <span class="rt-text">' . $termDefaultText . '</span>
								                        <i class="fa fa-angle-down rt-arrow-angle" aria-hidden="true"></i>
								                    </span>';

				$html .= $showAllhtml . $htmlButton;
				$html .= '</div>' . $selectedSubTerms;
			} else {
				//if Button the execute
				//$termDefaultText = $allText;

				$bCount = 0;
				$bItems = null;

				if ( ! empty( $terms ) ) {
					foreach ( $terms as $term_id ) {
						$term = get_term( $term_id, $taxFilter, ARRAY_A );
						if ( ! isset( $term['term_id'] ) ) {
							continue;
						}
						$id     = $term['term_id'];
						$bCount = $bCount + absint( $term['count'] );
						$sT     = null;
						if ( $data['tgp_filter_taxonomy_hierarchical'] == 'yes' && $data['filter_btn_style'] === 'default' && $data['filter_type'] == 'button' ) {
							$subTerms = Fns::rt_get_all_term_by_taxonomy( $taxFilter, true, $id );
							if ( ! empty( $subTerms ) ) {
								$sT .= "<div class='rt-filter-sub-tax sub-button-group '>";
								foreach ( $subTerms as $stId => $t ) {
									$sTPostCount = ( $post_count ? " (<span class='rt-post-count'>{$t['count']}</span>)" : null );
									$sT          .= "<span class='term-button-item rt-filter-button-item ' data-term='{$stId}'>{$t['name']}{$sTPostCount}</span>";
								}
								$sT .= "</div>";
								if ( $default_term === $id ) {
									$selectedSubTermsForButton = $sT;
								}
							}
						}
						$postCount    = ( $post_count ? " (<span class='rt-post-count'>{$term['count']}</span>)" : null );
						$termSelected = null;
						if ( $isTermSelected && $id == $default_term ) {
							$termSelected = " selected";
						}
						if ( is_array( $taxFilterTerms ) && ! empty( $taxFilterTerms ) ) {
							if ( in_array( $id, $taxFilterTerms ) ) {
								$bItems .= "<span class='term-button-item rt-filter-button-item {$termSelected} {$itemClass}' data-term='{$id}'>{$term['name']}{$postCount}{$sT}</span>";
							}
						} else {
							$bItems .= "<span class='term-button-item rt-filter-button-item {$termSelected} {$itemClass}' data-term='{$id}'>{$term['name']}{$postCount}{$sT}</span>";
						}
					}
				}
				$html .= "<div class='rt-filter-item-wrap rt-tax-filter rt-filter-button-wrap{$postCountClass} {$wrapperClass}' data-taxonomy='{$taxFilter}' data-filter='taxonomy'>";

				//$pCountH = ( $post_count ? " (<span class='rt-post-count'>{$bCount}</span>)" : null );
				if ( 'yes' == $data['tpg_hide_all_button'] ) {
					$html .= "<span class='term-button-item rt-filter-button-item {$allSelect} {$itemClass}' data-term='all'>" . $allText . "</span>";
				}

				$html .= $bItems;

				$html .= "</div>";
				if ( 'carousel' === $data['filter_btn_style'] ) {
					$html .= '<div class="swiper-navigation"><div class="swiper-button-prev slider-btn"></div><div class="swiper-button-next slider-btn"></div></div>';
				}
			}
		}

		// TODO: Author filter
		if ( 'show' == $data['show_author_filter'] ) {
			$user_el = $data['author'];

			$filterAuthors = $user_el;

			if ( ! empty( $user_el ) ) {
				$users = get_users( apply_filters( 'tpg_author_arg', [ 'include' => $user_el ] ) );
			} else {
				$users = get_users( apply_filters( 'tpg_author_arg', [] ) );
			}
			$allText   = $allText = $data['author_filter_all_text'] ? $data['author_filter_all_text'] : __( "All Users", "ai-content-generate" );
			$allSelect = " selected";
			//$isTermSelected = false;
			//				if ( $default_term && $taxFilter ) {
			$isTermSelected = true;
			//					$allSelect      = null;
			//				}
			if ( $filterType == 'dropdown' ) {
				$html            .= "<div class='rt-filter-item-wrap rt-author-filter rt-filter-dropdown-wrap parent-dropdown-wrap{$postCountClass}' data-filter='author'>";
				$termDefaultText = $allText;
				$dataAuthor      = 'all';
				$htmlButton      = "";
				$htmlButton      .= '<span class="author-dropdown rt-filter-dropdown">';
				$htmlButton      .= "<span class='term-dropdown-item rt-filter-dropdown-item' data-term='all'>" . $allText . "</span>";

				if ( ! empty( $users ) ) {
					foreach ( $users as $user ) {
						$user_post_count = false;
						$post_count ? "(" . count_user_posts( $user->ID, $data['post_type'] ) . ")" : null;
						if ( is_array( $filterAuthors ) && ! empty( $filterAuthors ) ) {
							if ( in_array( $user->ID, $filterAuthors ) ) {
								if ( $default_term == $user->ID ) {
									$termDefaultText = $user->display_name;
									$dataTerm        = $user->ID;
								} else {
									$htmlButton .= "<span class='term-dropdown-item rt-filter-dropdown-item' data-term='{$user->ID}'>{$user->display_name} <span class='rt-text'>{$user_post_count}</span></span>";
								}
							}
						} else {
							if ( $default_term == $user->ID ) {
								$termDefaultText = $user->display_name;
								$dataTerm        = $user->ID;
							} else {
								$htmlButton .= "<span class='term-dropdown-item rt-filter-dropdown-item' data-term='{$user->ID}'><span class='rt-text'>{$user->display_name} {$user_post_count}</span></span>";
							}
						}
					}
				}


				$htmlButton .= '</span>';

				$showAllhtml = '<span class="term-default rt-filter-dropdown-default" data-term="' . $dataAuthor . '">
								                        <span class="rt-text">' . $termDefaultText . '</span>
								                        <i class="fa fa-angle-down rt-arrow-angle" aria-hidden="true"></i>
								                    </span>';

				$html .= $showAllhtml . $htmlButton;
				$html .= '</div>';
			} else {
				$bCount = 0;
				$bItems = null;

				if ( ! empty( $users ) ) {
					foreach ( $users as $user ) {
						if ( is_array( $filterAuthors ) && ! empty( $filterAuthors ) ) {
							if ( in_array( $user->ID, $filterAuthors ) ) {
								$bItems .= "<span class='author-button-item rt-filter-button-item' data-term='{$user->ID}'>{$user->display_name}</span>";
							}
						} else {
							$bItems .= "<span class='author-button-item rt-filter-button-item' data-term='{$user->ID}'>{$user->display_name}</span>";
						}
					}
				}

				$html .= "<div class='rt-filter-item-wrap rt-author-filter rt-filter-button-wrap{$postCountClass}' data-filter='author'>";
				//					if ( 'yes' == $data['tax_filter_all_text'] ) {
				//$pCountH = ( $post_count ? " (<span class='rt-post-count'>{$bCount}</span>)" : null );
				$html .= "<span class='author-button-item rt-filter-button-item {$allSelect}' data-author='all'>" . $allText . "</span>";
				//					}
				$html .= $bItems;
				$html .= "</div>";
			}
		}


		if ( 'show' == $data['show_author_filter'] || 'show' == $data['show_taxonomy_filter'] ) {
			$html .= "</div>";
		}


		if ( 'show' == $data['show_order_by'] || 'show' == $data['show_sort_order'] || 'show' == $data['show_search'] ) {
			$html .= "<div class='filter-right-wrapper'>";
		}


		// TODO: Order Filter
		if ( 'show' == $data['show_sort_order'] ) {
			$action_order = ( $data['order'] ? strtoupper( $data['order'] ) : "DESC" );
			$html         .= '<div class="rt-filter-item-wrap rt-sort-order-action" data-filter="order">';
			$html         .= "<span class='rt-sort-order-action-arrow' data-sort-order='{$action_order}'>&nbsp;<span></span></span>";
			$html         .= '</div>';
		}

		//TODO: Orderby Filter
		if ( 'show' == $data['show_order_by'] ) {
			$wooFeature     = ( $data['post_type'] == "product" ? true : false );
			$orders         = Options::rtPostOrderBy( $wooFeature );
			$action_orderby = ( ! empty( $data['orderby'] ) ? $data['orderby'] : "none" );
			if ( $action_orderby == 'none' ) {
				$action_orderby_label = __( "Sort By", "ai-content-generate" );
			} else if ( in_array( $action_orderby, array_keys( Options::rtMetaKeyType() ) ) ) {
				$action_orderby_label = __( "Meta value", "ai-content-generate" );
			} else {
				$action_orderby_label = __( "By ", "ai-content-generate" ) . $action_orderby;
			}
			if ( $action_orderby !== 'none' ) {
				$orders['none'] = __( "Sort By", "ai-content-generate" );
			}
			$html .= '<div class="rt-filter-item-wrap rt-order-by-action rt-filter-dropdown-wrap" data-filter="orderby">';
			$html .= "<span class='order-by-default rt-filter-dropdown-default' data-order-by='{$action_orderby}'>
							                        <span class='rt-text-order-by'>{$action_orderby_label}</span>
							                        <i class='fa fa-angle-down rt-arrow-angle' aria-hidden='true'></i>
							                    </span>";
			$html .= '<span class="order-by-dropdown rt-filter-dropdown">';

			foreach ( $orders as $orderKey => $order ) {
				$html .= '<span class="order-by-dropdown-item rt-filter-dropdown-item" data-order-by="' . $orderKey . '">' . $order . '</span>';
			}
			$html .= '</span>';
			$html .= '</div>';
		}

		//TODO: Search Filter
		if ( 'show' == $data['show_search'] ) {
			$html .= '<div class="rt-filter-item-wrap rt-search-filter-wrap" data-filter="search">';
			$html .= sprintf( '<input type="text" class="rt-search-input" placeholder="%s">', esc_html__( "Search...", 'ai-content-generate' ) );
			$html .= "<span class='rt-action'>&#128269;</span>";
			$html .= "<span class='rt-loading'></span>";
			$html .= '</div>';
		}

		if ( 'show' == $data['show_order_by'] || 'show' == $data['show_sort_order'] || 'show' == $data['show_search'] ) {
			$html .= "</div>";
		}

		$html .= "</div>$selectedSubTermsForButton</div>";

		return $html;
	}


	/**
	 * Get Excluded Taxonomy
	 *
	 * @return string[]
	 */
	public static function get_excluded_taxonomy() {
		return [
			'post_format',
			'nav_menu',
			'link_category',
			'wp_theme',
			'elementor_library_type',
			'elementor_library_type',
			'elementor_library_category',
			'product_visibility',
			'product_shipping_class',
		];
	}

	/**
	 * Get Popup Modal Markup
	 */
	public static function get_modal_markup() {
		$html = null;
		$html .= '<div class="md-modal rt-md-effect" id="rt-modal">
                        <div class="md-content">
                            <div class="rt-md-content-holder"></div>
                            <div class="md-cls-btn">
                                <button class="md-close"><i class="fa fa-times" aria-hidden="true"></i></button>
                            </div>
                        </div>
                    </div>';
		$html .= "<div class='md-overlay'></div>";
		echo $html;
	}

	/**
	 * Get Archive page title
	 */
	public static function get_archive_title() {
		$queried_obj = get_queried_object();
		if ( is_tag() || is_category() ) {
			echo esc_html( $queried_obj->name );
		} else if ( is_author() ) {
			echo esc_html( $queried_obj->display_name );
		} else if ( is_date() ) {
			$year        = get_query_var( 'year' );
			$monthnum    = get_query_var( 'monthnum' );
			$day         = get_query_var( 'day' );
			$time_string = $year . '/' . $monthnum . '/' . $day;
			$time_stamp  = strtotime( $time_string );
			echo date( get_option( 'date_format' ), $time_stamp );
		}
	}

	/**
	 * Get Last Category ID
	 *
	 * @return mixed
	 */
	public static function get_last_category_id() {
		if ( is_archive() ) {
			return;
		}
		$categories = get_terms( [
			'taxonomy'   => 'category',
			'hide_empty' => false,
			'number'     => 1,
		] );
		if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
			return $categories[0]->term_id;
		}
	}

	/**
	 * @param $data
	 *
	 * @return string
	 */
	public static function get_dynamic_class_gutenberg( $data ) {
		$uniqueId     = isset( $data['uniqueId'] ) ? $data['uniqueId'] : null;
		$uniqueClass  = 'dowp-block-postgrid dowp-block-wrapper dowp-block-' . $uniqueId;
		$dynamicClass = $uniqueClass;
		$dynamicClass .= ! empty( $data['align'] ) ? ' align' . $data['align'] : null;
		$dynamicClass .= ! empty( $data['className'] ) ? ' ' . $data['className'] : null;
		$dynamicClass .= ! empty( $data['full_wrapper_align']['lg'] ) ? " dowp-wrapper-align-{$data['full_wrapper_align']['lg']}" : null;
		$dynamicClass .= ! empty( $data['filter_type'] ) ? " dowp-filter-type-{$data['filter_type']}" : null;
		$dynamicClass .= ! empty( $data['show_pagination'] ) ? " pagination-visibility-{$data['show_pagination']}" : null;
		$dynamicClass .= ! empty( $data['ajax_pagination_type'] ) ? " ajax-pagination-type-next-prev-{$data['ajax_pagination_type']}" : null;
		$dynamicClass .= ! empty( $data['show_meta'] ) ? " meta-visibility-{$data['show_meta']}" : null;
		$dynamicClass .= ! empty( $data['section_title_style'] ) ? " section-title-style-{$data['section_title_style']}" : null;
		$dynamicClass .= ! empty( $data['section_title_alignment'] ) ? " section-title-align-{$data['section_title_alignment']}" : null;
		$dynamicClass .= ! empty( $data['hover_animation'] ) ? " img_hover_animation_{$data['hover_animation']}" : null;
		$dynamicClass .= ! empty( $data['title_visibility_style'] ) ? " title-{$data['title_visibility_style']}" : null;
		$dynamicClass .= ! empty( $data['title_position'] ) ? " title_position_{$data['title_position']}" : null;
		$dynamicClass .= ! empty( $data['title_hover_underline'] ) ? " title_hover_border_{$data['title_hover_underline']}" : null;
		$dynamicClass .= ! empty( $data['meta_position'] ) ? " meta_position_{$data['meta_position']}" : null;
		$dynamicClass .= ! empty( $data['author_icon_visibility'] ) ? " dowp-is-author-icon-{$data['author_icon_visibility']}" : null;
		$dynamicClass .= ! empty( $data['show_author_image'] ) ? " author-image-visibility-{$data['show_author_image']}" : null;
		$dynamicClass .= ! empty( $data['category_position'] ) ? " dowp-category-position-{$data['category_position']}" : null;
		$dynamicClass .= ! empty( $data['readmore_btn_style'] ) ? " readmore-btn-{$data['readmore_btn_style']}" : null;
		$dynamicClass .= ! empty( $data['grid_hover_overlay_type'] ) ? " grid-hover-overlay-type-{$data['grid_hover_overlay_type']}" : null;
		$dynamicClass .= ! empty( $data['grid_hover_overlay_height'] ) ? " grid-hover-overlay-height-{$data['grid_hover_overlay_height']}" : null;
		$dynamicClass .= ! empty( $data['on_hover_overlay'] ) ? " hover-overlay-height-{$data['on_hover_overlay']}" : null;
		$dynamicClass .= ! empty( $data['title_border_visibility'] ) ? " dowp-title-border-{$data['title_border_visibility']}" : null;
		$dynamicClass .= ! empty( $data['title_alignment'] ) ? " title-alignment-{$data['title_alignment']}" : null;
		$dynamicClass .= ! empty( $data['filter_v_alignment'] ) ? " dowp-filter-alignment-{$data['filter_v_alignment']}" : null;
		$dynamicClass .= ! empty( $data['border_style'] ) ? " filter-button-border-{$data['border_style']}" : null;
		$dynamicClass .= ! empty( $data['filter_next_prev_btn'] ) ? " filter-nex-prev-btn-{$data['filter_next_prev_btn']}" : null;
		$dynamicClass .= ! empty( $data['filter_h_alignment'] ) ? " dowp-filter-h-alignment-{$data['filter_h_alignment']}" : null;
		$dynamicClass .= ! empty( $data['is_box_border'] ) ? " dowp-el-box-border-{$data['is_box_border']}" : null;
		$dynamicClass .= ! empty( $data['category_style'] ) ? " dowp-cat-{$data['category_style']}" : null;

		//Slider layout
		$dynamicClass .= ! empty( $data['arrow_position'] ) ? " slider-arrow-position-{$data['arrow_position']}" : null;
		$dynamicClass .= ! empty( $data['dots'] ) ? " slider-dot-enable-{$data['dots']}" : null;
		$dynamicClass .= ! empty( $data['dots_style'] ) ? " slider-dots-style-{$data['dots_style']}" : null;
		$dynamicClass .= ! empty( $data['lazyLoad'] ) ? " is-lazy-load-{$data['lazyLoad']}" : null;
		$dynamicClass .= ! empty( $data['carousel_overflow'] ) ? " is-carousel-overflow-{$data['carousel_overflow']}" : null;
		$dynamicClass .= ! empty( $data['slider_direction'] ) ? " slider-direction-{$data['slider_direction']}" : null;
		$dynamicClass .= ! empty( $data['dots_text_align'] ) ? " slider-dots-align-{$data['dots_text_align']}" : null;
		$dynamicClass .= ! empty( $data['pagination_btn_space_btween'] ) && $data['pagination_btn_space_btween'] === 'space-between' ? " dowp-prev-next-space-between" : null;
		$dynamicClass .= ! empty( $data['pagination_btn_position'] ) && $data['pagination_btn_position'] === 'absolute' ? " dowp-prev-next-absolute" : null;
		$dynamicClass .= ! empty( $data['box_border_bottom'] ) && $data['box_border_bottom'] === 'enable' ? " dowp-border-bottom-enable" : null;
		$dynamicClass .= ! empty( $data['offset_img_position'] ) && $data['offset_img_position'] === 'offset-image-right' ? " offset-image-right" : null;
		$dynamicClass .= ! empty( $data['scroll_visibility'] ) && $data['scroll_visibility'] === 'yes' ? "" : " slider-scroll-hide";
		$dynamicClass .= ! empty( $data['enable_external_link'] ) && $data['enable_external_link'] === 'show' ? " has-external-link" : "";

		//ACF
		$dynamicClass .= ! empty( $data['acf_label_style'] ) ? " act-label-style-{$data['acf_label_style']}" : null;
		$dynamicClass .= ! empty( $data['acf_alignment'] ) && ! is_array( $data['acf_alignment'] ) ? " dowp-acf-align-{$data['acf_alignment']}" : null;

		return $dynamicClass;
	}


	/**
	 * Get Section Title
	 *
	 * @param $data
	 */
	public static function get_section_title( $data ) {
		if ( 'show' != $data['show_section_title'] ) {
			return;
		}

		$_is_link = false;
		if ( ! empty( $data['section_title_link']['url'] ) ) {
			$_is_link = true;
		}

		ob_start();
		?>

        <div class="dowp-widget-heading-wrapper rt-clear heading-<?php echo esc_attr( $data['section_title_style'] ); ?> ">
            <span class="dowp-widget-heading-line line-left"></span>

			<?php printf( "<%s class='dowp-widget-heading'>", $data['section_title_tag'] ); ?>

			<?php
			if ( $_is_link ) : ?>
            <a href="#">
				<?php endif; ?>

				<?php
				if ( 'page_title' == $data['section_title_source'] ) {
					$archive_prefix = $data['title_prefix'] ? $data['title_prefix'] . ' ' : null;
					$archive_suffix = $data['title_suffix'] ? ' ' . $data['title_suffix'] : null;
					printf( "<span class='prefix-text'>%s</span>", esc_html( $archive_prefix ) );
					if ( is_archive() ) {
						self::get_archive_title();
					} else if ( is_search() ) {
						echo get_query_var( 's' );
					} else {
						the_title();
					}
					printf( "<span class='suffix-text'>%s</span>", esc_html( $archive_suffix ) );
				} else {
					?>
                    <span>
                        <?php echo $data['section_title_text'] ?>
                    </span>
					<?php
				}
				?>

				<?php if ( $_is_link ) : ?>
            </a>

		<?php endif; ?>
			<?php printf( "</%s>", $data['section_title_tag'] ); ?>
            <span class="dowp-widget-heading-line line-right"></span>

			<?php if ( isset( $data['enable_external_link'] ) && 'show' === $data['enable_external_link'] ) : ?>
                <a class='external-link' href='<?php echo esc_url( $data['section_external_link'] ?? '#' ) ?>'>
					<?php if ( $data['section_external_text'] ) : ?>
                        <span class="external-lable"><?php echo esc_html( $data['section_external_text'] ) ?></span>
					<?php endif; ?>
					<?php echo "<i class='left-icon " . Fns::change_icon( 'fas fa-angle-right', 'right-arrow', 'left-icon' ) . "'></i>"; ?>
                </a>
			<?php endif; ?>

        </div>

		<?php if ( isset( $data['show_cat_desc'] ) && $data['show_cat_desc'] == 'yes' && category_description( self::get_last_category_id() ) ) : ?>
            <div class="dowp-category-description">
				<?php echo category_description( self::get_last_category_id() ); ?>
            </div>
		<?php endif; ?>

		<?php echo ob_get_clean();
	}

	/**
	 * rtAllOptionFields
	 * All settings.
	 *
	 * @return array
	 */
	public static function rtAllOptionFields() {
		$fields = array_merge(
			Options::dwpAICCommonFilterFields(),
			Options::dwpAICLayoutSettingFields(),
			Options::responsiveSettingsColumn(),
			Options::layoutMiscSettings(),
			Options::stickySettings(),
			// settings.
			Options::dwpAICSCHeadingSettings(),
			Options::dwpAICSCCategorySettings(),
			Options::dwpAICSCTitleSettings(),
			Options::dwpAICSCMetaSettings(),
			Options::dwpAICSCImageSettings(),
			Options::dwpAICSCExcerptSettings(),
			Options::dwpAICSCButtonSettings(),
			// style.
			Options::dwpAICStyleFields(),
			Options::dwpAICStyleHeading(),
			Options::dwpAICStyleFullArea(),
			Options::dwpAICStyleContentWrap(),
			Options::dwpAICStyleCategory(),
			Options::dwpAICPostType(),
			Options::dwpAICStyleButtonColorFields(),
			Options::rtTPAdvanceFilters(),
			Options::itemFields()
		);

		return $fields;
	}

	public static function rt_get_all_term_by_taxonomy( $taxonomy = null, $count = false, $parent = false ) {
		$terms = [];

		if ( $taxonomy ) {
			$temp_terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => 0,
				]
			);

			if ( is_array( $temp_terms ) && ! empty( $temp_terms ) && empty( $temp_terms['errors'] ) ) {
				foreach ( $temp_terms as $term ) {
					$order = get_term_meta( $term->term_id, '_rt_order', true );
					if ( $order === '' ) {
						update_term_meta( $term->term_id, '_rt_order', 0 );
					}
				}

				global $wp_version;

				$args = [
					'taxonomy'   => $taxonomy,
					'orderby'    => 'meta_value_num',
					'meta_key'   => '_rt_order',
					'hide_empty' => apply_filters( 'dowp_category_hide_empty', false ),
				];

				if ( $parent >= 0 && $parent !== false ) {
					$args['parent'] = absint( $parent );
				}

				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_rt_order';

				$termObjs = get_terms( $args );

				foreach ( $termObjs as $term ) {
					if ( $count ) {
						$terms[ $term->term_id ] = [
							'name'  => $term->name,
							'count' => $term->count,
						];
					} else {
						$terms[ $term->term_id ] = $term->name;
					}
				}
			}
		}

		return $terms;
	}

	public static function rt_get_selected_term_by_taxonomy( $taxonomy = null, $include = [], $count = false, $parent = false ) {
		$terms = [];

		if ( $taxonomy ) {
			$temp_terms = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => 0,
				]
			);

			if ( is_array( $temp_terms ) && ! empty( $temp_terms ) && empty( $temp_terms['errors'] ) ) {
				foreach ( $temp_terms as $term ) {
					$order = get_term_meta( $term->term_id, '_rt_order', true );
					if ( $order === '' ) {
						update_term_meta( $term->term_id, '_rt_order', 0 );
					}
				}

				global $wp_version;

				$args = [
					'taxonomy'   => $taxonomy,
					'orderby'    => 'meta_value_num',
					'meta_key'   => '_rt_order',
					'include'    => $include,
					'hide_empty' => false,
				];

				if ( $parent >= 0 && $parent !== false ) {
					$args['parent'] = absint( $parent );
				}

				$args['orderby']  = 'meta_value_num';
				$args['meta_key'] = '_rt_order';

				$termObjs = get_terms( $args );

				foreach ( $termObjs as $term ) {
					if ( $count ) {
						$terms[ $term->term_id ] = [
							'name'  => $term->name,
							'count' => $term->count,
						];
					} else {
						$terms[ $term->term_id ] = $term->name;
					}
				}
			}
		}

		return $terms;
	}

	public static function getCurrentUserRoles() {
		global $current_user;

		return $current_user->roles;
	}

	public static function rt_get_taxonomy_for_filter( $post_type = null ) {
		if ( ! $post_type ) {
			$post_type = get_post_meta( get_the_ID(), 'tpg_post_type', true );
		}

		if ( ! $post_type ) {
			$post_type = 'post';
		}

		return self::rt_get_all_taxonomy_by_post_type( $post_type );
	}

	public static function rt_get_all_taxonomy_by_post_type( $post_type = null ) {
		$taxonomies = [];

		if ( $post_type && post_type_exists( $post_type ) ) {
			$taxObj = get_object_taxonomies( $post_type, 'objects' );

			if ( is_array( $taxObj ) && ! empty( $taxObj ) ) {
				foreach ( $taxObj as $tKey => $taxonomy ) {
					$taxonomies[ $tKey ] = $taxonomy->label;
				}
			}
		}

		if ( $post_type == 'post' ) {
			unset( $taxonomies['post_format'] );
		}

		return $taxonomies;
	}

	public static function rt_get_users() {
		$users = [];
		$u     = get_users( apply_filters( 'tpg_author_arg', [] ) );

		if ( ! empty( $u ) ) {
			foreach ( $u as $user ) {
				$users[ $user->ID ] = $user->display_name;
			}
		}

		return $users;
	}

	public static function rtFieldGenerator( $fields = [] ) {
		$html = null;

		if ( is_array( $fields ) && ! empty( $fields ) ) {
			$tpgField = new Field();
			foreach ( $fields as $fieldKey => $field ) {
				$html .= $tpgField->Field( $fieldKey, $field );
			}
		}

		return $html;
	}

	/**
	 * Sanitize field value
	 *
	 * @param array $field
	 * @param null $value
	 *
	 * @return array|null
	 * @internal param $value
	 */
	public static function sanitize( $field = [], $value = null ) {
		$newValue = null;

		if ( is_array( $field ) ) {
			$type = ( ! empty( $field['type'] ) ? $field['type'] : 'text' );

			if ( empty( $field['multiple'] ) ) {
				if ( $type == 'text' || $type == 'number' || $type == 'select' || $type == 'checkbox' || $type == 'radio' ) {
					$newValue = sanitize_text_field( $value );
				} else if ( $type == 'url' ) {
					$newValue = esc_url( $value );
				} else if ( $type == 'slug' ) {
					$newValue = sanitize_title_with_dashes( $value );
				} else if ( $type == 'textarea' ) {
					$newValue = wp_kses_post( $value );
				} else if ( $type == 'script' ) {
					$newValue = trim( $value );
				} else if ( $type == 'colorpicker' ) {
					$newValue = self::sanitize_hex_color( $value );
				} else if ( $type == 'image_size' ) {
					$newValue = [];

					foreach ( $value as $k => $v ) {
						$newValue[ $k ] = esc_attr( $v );
					}
				} else if ( $type == 'style' ) {
					$newValue = [];

					foreach ( $value as $k => $v ) {
						if ( $k == 'color' ) {
							$newValue[ $k ] = self::sanitize_hex_color( $v );
						} else {
							$newValue[ $k ] = self::sanitize( [ 'type' => 'text' ], $v );
						}
					}
				} else {
					$newValue = sanitize_text_field( $value );
				}
			} else {
				$newValue = [];

				if ( ! empty( $value ) ) {
					if ( is_array( $value ) ) {
						foreach ( $value as $key => $val ) {
							if ( $type == 'style' && $key == 0 ) {
								if ( function_exists( 'sanitize_hex_color' ) ) {
									$newValue = sanitize_hex_color( $val );
								} else {
									$newValue[] = self::sanitize_hex_color( $val );
								}
							} else {
								$newValue[] = sanitize_text_field( $val );
							}
						}
					} else {
						$newValue[] = sanitize_text_field( $value );
					}
				}
			}
		}

		return $newValue;
	}

	public static function sanitize_hex_color( $color ) {
		if ( function_exists( 'sanitize_hex_color' ) ) {
			return sanitize_hex_color( $color );
		} else {
			if ( '' === $color ) {
				return '';
			}

			// 3 or 6 hex digits, or the empty string.
			if ( preg_match( '|^#([A-Fa-f0-9]{3}){1,2}$|', $color ) ) {
				return $color;
			}
		}
	}

	public static function rtFieldGeneratorBackup( $fields = [], $multi = false ) {
		$html = null;

		if ( is_array( $fields ) && ! empty( $fields ) ) {
			$rtField = new Field();

			if ( $multi ) {
				foreach ( $fields as $field ) {
					$html .= $rtField->Field( $field );
				}
			} else {
				$html .= $rtField->Field( $fields );
			}
		}

		return $html;
	}

	public static function rtSmartStyle( $fields = [] ) {
		$h = null;

		if ( ! empty( $fields ) ) {
			foreach ( $fields as $key => $label ) {
				$atts    = '';
				$proText = '';
				$class   = '';

				$h .= '<div class="field-holder ' . esc_attr( $class ) . '">';

				$h .= '<div class="field-label"><label>' . esc_html( $label ) . '' . self::htmlKses( $proText, 'basic' ) . '</label></div>';
				$h .= "<div class='field'>";
				// color.
				$h      .= "<div class='field-inner col-4'>";
				$h      .= "<div class='field-inner-container size'>";
				$h      .= "<span class='label'>Color</span>";
				$cValue = get_post_meta( get_the_ID(), $key . '_color', true );
				$h      .= '<input type="text" value="' . esc_attr( $cValue ) . '" class="rt-color" name="' . esc_attr( $key ) . '_color">';
				$h      .= '</div>';
				$h      .= '</div>';

				// Font size.
				$h      .= "<div class='field-inner col-4'>";
				$h      .= "<div class='field-inner-container size'>";
				$h      .= "<span class='label'>Font size</span>";
				$h      .= '<select ' . self::htmlKses( $atts, 'basic' ) . ' name="' . esc_attr( $key ) . '_size" class="rt-select2">';
				$fSizes = Options::scFontSize();
				$sValue = get_post_meta( get_the_ID(), $key . '_size', true );
				$h      .= "<option value=''>Default</option>";

				foreach ( $fSizes as $size => $sizeLabel ) {
					$sSlt = ( $size == $sValue ? 'selected' : null );
					$h    .= '<option value="' . esc_attr( $size ) . '" ' . esc_attr( $sSlt ) . '>' . esc_html( $sizeLabel ) . '</option>';
				}

				$h .= '</select>';
				$h .= '</div>';
				$h .= '</div>';

				// Weight.
				$h       .= "<div class='field-inner col-4'>";
				$h       .= "<div class='field-inner-container weight'>";
				$h       .= "<span class='label'>Weight</span>";
				$h       .= '<select ' . self::htmlKses( $atts, 'basic' ) . ' name="' . esc_attr( $key ) . '_weight" class="rt-select2">';
				$h       .= "<option value=''>Default</option>";
				$weights = Options::scTextWeight();
				$wValue  = get_post_meta( get_the_ID(), $key . '_weight', true );

				foreach ( $weights as $weight => $weightLabel ) {
					$wSlt = ( $weight == $wValue ? 'selected' : null );
					$h    .= '<option value="' . esc_attr( $weight ) . '" ' . esc_attr( $wSlt ) . '>' . esc_html( $weightLabel ) . '</option>';
				}

				$h .= '</select>';
				$h .= '</div>';
				$h .= '</div>';

				// Alignment.
				$h      .= "<div class='field-inner col-4'>";
				$h      .= "<div class='field-inner-container alignment'>";
				$h      .= "<span class='label'>Alignment</span>";
				$h      .= '<select ' . self::htmlKses( $atts, 'basic' ) . ' name="' . esc_attr( $key ) . '_alignment" class="rt-select2">';
				$h      .= "<option value=''>Default</option>";
				$aligns = Options::scAlignment();
				$aValue = get_post_meta( get_the_ID(), $key . '_alignment', true );

				foreach ( $aligns as $align => $alignLabel ) {
					$aSlt = ( $align == $aValue ? 'selected' : null );
					$h    .= '<option value="' . esc_attr( $align ) . '" ' . esc_attr( $aSlt ) . '>' . esc_html( $alignLabel ) . '</option>';
				}

				$h .= '</select>';
				$h .= '</div>';
				$h .= '</div>';

				$h .= '</div>';
				$h .= '</div>';
			}
		}

		return $h;
	}

	public static function custom_variation_price( $product ) {
		$price = '';
		$max   = $product->get_variation_sale_price( 'max' );
		$min   = $product->get_variation_sale_price( 'min' );

		if ( ! $min || $min !== $max ) {
			$price .= wc_price( $product->get_price() );
		}

		if ( $max && $max !== $min ) {
			$price .= ' - ';
			$price .= wc_price( $max );
		}

		return $price;
	}

	public static function getTPGShortCodeList() {
		$scList = null;
		$scQ    = get_posts(
			[
				'post_type'      => dwpAIC()->post_type,
				'order_by'       => 'title',
				'order'          => 'DESC',
				'post_status'    => 'publish',
				'posts_per_page' => - 1,
				'meta_query'     => [
					[
						'key'     => 'layout',
						'value'   => 'layout',
						'compare' => 'LIKE',
					],
				],
			]
		);

		if ( ! empty( $scQ ) ) {
			foreach ( $scQ as $sc ) {
				$scList[ $sc->ID ] = $sc->post_title;
			}
		}

		return $scList;
	}

	public static function getAllTPGShortCodeList() {
		$scList = null;
		$scQ    = get_posts(
			[
				'post_type'      => dwpAIC()->post_type,
				'order_by'       => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
				'posts_per_page' => - 1,
			]
		);
		if ( ! empty( $scQ ) ) {
			foreach ( $scQ as $sc ) {
				$scList[ $sc->ID ] = $sc->post_title;
			}
		}

		return $scList;
	}

	public static function socialShare( $pLink ) {
		$html = null;
		$html .= "<div class='single-dowp-share'>
					<div class='fb-share'>
						<div class='fb-share-button' data-href='" . esc_url( $pLink ) . "' data-layout='button_count'></div>
					</div>
					<div class='twitter-share'>
						<a href='" . esc_url( $pLink ) . "' class='twitter-share-button'{count} data-url='https://about.twitter.com/resources/buttons#tweet'>Tweet</a>
					</div>
					<div class='googleplus-share'>
						<div class='g-plusone'></div>
					</div>
					<div class='linkedin-share'>
						<script type='IN/Share' data-counter='right'></script>
					</div>
					<div class='linkedin-share'>
						<a data-pin-do='buttonPin' data-pin-count='beside' href='https://www.pinterest.com/pin/create/button/?url=https%3A%2F%2Fwww.flickr.com%2Fphotos%2Fkentbrew%2F6851755809%2F&media=https%3A%2F%2Ffarm8.staticflickr.com%2F7027%2F6851755809_df5b2051c9_z.jpg&description=Next%20stop%3A%20Pinterest'><img src='//assets.pinterest.com/images/pidgets/pinit_fg_en_rect_gray_20.png' /></a>
					</div>
				</div>";
		$html .= '<div id="fb-root"></div>
					<script>(function(d, s, id) {
						var js, fjs = d.getElementsByTagName(s)[0];
							if (d.getElementById(id)) return;
							js = d.createElement(s); js.id = id;
							js.src = "//connect.facebook.net/en_US/sdk.js#xfbml=1&version=v2.5";
							fjs.parentNode.insertBefore(js, fjs);
						}(document, "script", "facebook-jssdk"));</script>';
		$html .= "<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?'http':'https';if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+'://platform.twitter.com/widgets.js';fjs.parentNode.insertBefore(js,fjs);}}(document, 'script', 'twitter-wjs');</script>
            <script>window.___gcfg = { lang: 'en-US', parsetags: 'onload', };</script>";
		$html .= "<script src='https://apis.google.com/js/platform.js' async defer></script>";
		$html .= '<script src="//platform.linkedin.com/in.js" type="text/javascript"> lang: en_US</script>';
		$html .= '<script async defer src="//assets.pinterest.com/js/pinit.js"></script>';

		return $html;
	}

	public static function get_image_sizes() {
		global $_wp_additional_image_sizes;

		$sizes      = [];
		$interSizes = get_intermediate_image_sizes();
		if ( ! empty( $interSizes ) ) {
			foreach ( get_intermediate_image_sizes() as $_size ) {
				if ( in_array( $_size, [ 'thumbnail', 'medium', 'large' ] ) ) {
					$sizes[ $_size ]['width']  = get_option( "{$_size}_size_w" );
					$sizes[ $_size ]['height'] = get_option( "{$_size}_size_h" );
					$sizes[ $_size ]['crop']   = (bool) get_option( "{$_size}_crop" );
				} else if ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
					$sizes[ $_size ] = [
						'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
						'height' => $_wp_additional_image_sizes[ $_size ]['height'],
						'crop'   => $_wp_additional_image_sizes[ $_size ]['crop'],
					];
				}
			}
		}

		$imgSize = [];

		if ( ! empty( $sizes ) ) {
			$imgSize['full'] = esc_html__( 'Full Size', 'ai-content-generate' );
			foreach ( $sizes as $key => $img ) {
				$imgSize[ $key ] = ucfirst( $key ) . " ({$img['width']}*{$img['height']})";
			}
		}

		return apply_filters( 'tpg_image_sizes', $imgSize );
	}

	public static function getFeatureImageSrc(
		$post_id = null,
		$fImgSize = 'medium',
		$mediaSource = 'feature_image',
		$defaultImgId = null,
		$customImgSize = [],
		$img_Class = ''
	) {
		global $post;

		$imgSrc    = null;
		$img_class = 'rt-img-responsive ';

		if ( $img_Class ) {
			$img_class .= $img_Class;
		}

		$post_id = ( $post_id ? absint( $post_id ) : $post->ID );
		$alt     = get_the_title( $post_id );
		$image   = null;
		$cSize   = false;

		if ( $fImgSize == 'rt_custom' ) {
			$fImgSize = 'full';
			$cSize    = true;
		}

		if ( $mediaSource == 'feature_image' ) {
			if ( $aID = get_post_thumbnail_id( $post_id ) ) {
				$image  = wp_get_attachment_image(
					$aID,
					$fImgSize,
					'',
					[
						'class'   => $img_class,
						'loading' => false,
					]
				);
				$imgSrc = wp_get_attachment_image_src( $aID, $fImgSize );

				if ( ! empty( $imgSrc ) && $img_Class == 'swiper-lazy' ) {
					$image = '<img class="' . esc_attr( $img_class ) . '" data-src="' . esc_url( $imgSrc[0] ) . '" src="#none" width="' . absint( $imgSrc[1] ) . '" height="' . absint( $imgSrc[2] ) . '" alt="' . esc_attr( $alt ) . '"/><div class="lazy-overlay-wrap"><div class="swiper-lazy-preloader swiper-lazy-preloader-white"></div></div>';
				}

				$imgSrc = ! empty( $imgSrc ) ? $imgSrc[0] : $imgSrc;
			}
		} else if ( $mediaSource == 'first_image' ) {
			if ( $img = preg_match_all(
				'/<img.+src=[\'"]([^\'"]+)[\'"].*>/i',
				get_the_content( $post_id ),
				$matches
			)
			) {
				$imgSrc = $matches[1][0];
				$size   = '';

				if ( strpos( $imgSrc, site_url() ) !== false ) {
					$imgAbs = str_replace( trailingslashit( site_url() ), ABSPATH, $imgSrc );
				} else {
					$imgAbs = ABSPATH . $imgSrc;
				}

				$imgAbs = apply_filters( 'rt_tpg_sc_first_image_src', $imgAbs );

				if ( file_exists( $imgAbs ) ) {
					$info = getimagesize( $imgAbs );
					$size = isset( $info[3] ) ? $info[3] : '';
				}

				$image = '<img class="' . esc_attr( $img_class ) . '" src="' . esc_url( $imgSrc ) . '" ' . $size . ' alt="' . esc_attr( $alt ) . '"/>';

				if ( $img_Class == 'swiper-lazy' ) {
					$image = '<img class="' . esc_attr( $img_class ) . ' img-responsive" data-src="' . esc_url( $imgSrc ) . '" src="#none" ' . $size . ' alt="' . esc_attr( $alt ) . '"/><div class="lazy-overlay-wrap"><div class="swiper-lazy-preloader swiper-lazy-preloader-white"></div></div>';
				}
			}
		}

		if ( ! $imgSrc && $defaultImgId ) {
			$image = wp_get_attachment_image( $defaultImgId, $fImgSize );
		}

		if ( $imgSrc && $cSize ) {
			$w = ( ! empty( $customImgSize[0] ) ? absint( $customImgSize[0] ) : null );
			$h = ( ! empty( $customImgSize[1] ) ? absint( $customImgSize[1] ) : null );
			$c = ( ! empty( $customImgSize[2] ) && $customImgSize[2] == 'soft' ? false : true );

			if ( $w && $h ) {
				$post_thumb_id = get_post_thumbnail_id( $post_id );

				if ( $post_thumb_id ) {
					$featured_image = wp_get_attachment_image_src( $post_thumb_id, 'full' );
					$w              = $featured_image[1] < $w ? $featured_image[1] : $w;
					$h              = $featured_image[2] < $h ? $featured_image[2] : $h;
				}

				$imgSrc = self::rtImageReSize( $imgSrc, $w, $h, $c );

				if ( $img_Class !== 'swiper-lazy' ) {
					$image = '<img class="' . esc_attr( $img_class ) . '" src="' . esc_url( $imgSrc ) . '" width="' . absint( $w ) . '" height="' . absint( $h ) . '" alt="' . esc_attr( $alt ) . '"/>';
				} else {
					$image = '<img class="' . esc_attr( $img_class ) . ' img-responsive" data-src="' . esc_url( $imgSrc ) . '" src="#none" width="' . absint( $w ) . '" height="' . absint( $h ) . '" alt="' . esc_attr( $alt ) . '"/><div class="lazy-overlay-wrap"><div class="swiper-lazy-preloader swiper-lazy-preloader-white"></div></div>';
				}
			}
		}

		return $image;
	}

	public static function getFeatureImageUrl( $post_id = null, $fImgSize = 'medium' ) {
		$image = $imgSrc = null;

		if ( $aID = get_post_thumbnail_id( $post_id ) ) {
			$image = wp_get_attachment_image_src( $aID, $fImgSize );
		}

		if ( is_array( $image ) ) {
			$imgSrc = $image[0];
		}

		return $imgSrc;
	}

	public static function tpgCharacterLimit( $limit, $content ) {
		$limit ++;

		$text = '';

		if ( mb_strlen( $content ) > $limit ) {
			$subex   = mb_substr( $content, 0, $limit );
			$exwords = explode( ' ', $subex );
			$excut   = - ( mb_strlen( $exwords[ count( $exwords ) - 1 ] ) );

			if ( $excut < 0 ) {
				$text = mb_substr( $subex, 0, $excut );
			} else {
				$text = $subex;
			}
		} else {
			$text = $content;
		}

		return $text;
	}

	public static function get_the_excerpt( $post_id, $data = [] ) {
		$type = $data['excerpt_type'];
		$post = get_post( $post_id );

		if ( empty( $post ) ) {
			return '';
		}

		if ( $type == 'full' ) {
			ob_start();
			the_content();
			$content = ob_get_clean();

			return apply_filters( 'tpg_content_full', $content, $post_id, $data );
		} else {
			if ( class_exists( 'ET_GB_Block_Layout' ) ) {
				$defaultExcerpt = $post->post_excerpt ?: wp_trim_words( $post->post_content, 55 );
			} else if ( defined( 'WPB_VC_VERSION' ) ) {
				$the_content    = $post->post_excerpt ?: wp_trim_words( $post->post_content, 55 );
				$shortcode_tags = [ 'VC_COLUMN_INNTER' ];
				$values         = array_values( $shortcode_tags );
				$exclude_codes  = implode( '|', $values );
				$defaultExcerpt = trim( preg_replace( "~(?:\[/?)(?!(?:$exclude_codes))[^/\]]+/?\]~s", '', $the_content ) );
			} else {
				$defaultExcerpt = get_the_excerpt( $post_id );
			}

			$limit   = isset( $data['excerpt_limit'] ) && $data['excerpt_limit'] ? abs( $data['excerpt_limit'] ) : 0;
			$more    = $data['excerpt_more_text'];
			$excerpt = preg_replace( '`\[[^\]]*\]`', '', $defaultExcerpt );
			$excerpt = strip_shortcodes( $excerpt );
			$excerpt = preg_replace( '`[[^]]*]`', '', $excerpt );
			$excerpt = str_replace( '…', '', $excerpt );

			if ( $limit ) {
				$excerpt = wp_strip_all_tags( $excerpt );

				if ( $type == 'word' ) {
					$limit      = $limit + 1;
					$rawExcerpt = $excerpt;
					$excerpt    = explode( ' ', $excerpt, $limit );

					if ( count( $excerpt ) >= $limit ) {
						array_pop( $excerpt );
						$excerpt = implode( ' ', $excerpt );
					} else {
						$excerpt = $rawExcerpt;
					}
				} else {
					$excerpt = self::tpgCharacterLimit( $limit, $excerpt );
				}
				$excerpt = stripslashes( $excerpt );
			} else {
				$allowed_html = [
					'a'      => [
						'href'  => [],
						'title' => [],
					],
					'strong' => [],
					'b'      => [],
					'br'     => [ [] ],
				];

				$excerpt = nl2br( wp_kses( $excerpt, $allowed_html ) );
			}

			$excerpt = ( $more ? rtrim( $excerpt, " .,-_" ) . $more : $excerpt );

			return apply_filters( 'tpg_get_the_excerpt', $excerpt, $post_id, $data, $defaultExcerpt );
		}
	}

	public static function get_the_title( $post_id, $data = [] ) {
		$title      = $originalTitle = get_the_title( $post_id );
		$limit      = isset( $data['title_limit'] ) ? absint( $data['title_limit'] ) : 0;
		$limit_type = isset( $data['title_limit_type'] ) ? trim( $data['title_limit_type'] ) : 'character';

		if ( $limit ) {
			if ( $limit_type == 'word' ) {
				$limit = $limit + 1;
				$title = explode( ' ', $title, $limit );

				if ( count( $title ) >= $limit ) {
					array_pop( $title );
					$title = implode( ' ', $title );
				} else {
					$title = $originalTitle;
				}
			} else {
				if ( $limit > 0 && strlen( $title ) > $limit ) {
					$title = mb_substr( $title, 0, $limit, 'utf-8' );
					$title = preg_replace( '/\W\w+\s*(\W*)$/', '$1', $title );
				}
			}
		}

		return apply_filters( 'tpg_get_the_title', $title, $post_id, $data, $originalTitle );
	}


	public static function rt_pagination( $postGrid, $range = 4, $ajax = false ) {
		$range = 4;
		if ( ! empty( self::tpg_option( 'tpg_pagination_range' ) ) ) {
			$range = self::tpg_option( 'tpg_pagination_range' );
		}
		$html      = null;
		$showitems = ( $range * 2 ) + 1;

		$wpQuery = $postGrid;

		global $wp_query;

		if ( empty( $wpQuery ) ) {
			$wpQuery = $wp_query;
		}

		$pages = ! empty( $wpQuery->max_num_pages ) ? $wpQuery->max_num_pages : 1;
		$paged = ! empty( $wpQuery->query['paged'] ) ? $wpQuery->query['paged'] : 1;

		if ( is_front_page() ) {
			$paged = ! empty( $wp_query->query['paged'] ) ? $wp_query->query['paged'] : 1;
		}

		$ajaxClass = null;
		$dataAttr  = null;

		if ( $ajax ) {
			$ajaxClass = ' rt-ajax';
			$dataAttr  = "data-paged='1'";
		}

		if ( 1 != $pages ) {
			$html .= '<div class="rt-pagination' . $ajaxClass . '" ' . $dataAttr . '>';
			$html .= '<ul class="pagination-list">';
			if ( $paged > 2 && $paged > $range + 1 && $showitems < $pages && ! $ajax ) {
				$html .= "<li><a data-paged='1' href='" . get_pagenum_link( 1 ) . "' aria-label='First'>&laquo;</a></li>";
			}

			if ( $paged > 1 && $showitems < $pages && ! $ajax ) {
				$p    = $paged - 1;
				$html .= "<li><a data-paged='{$p}' href='" . get_pagenum_link( $p ) . "' aria-label='Previous'>&lsaquo;</a></li>";
			}

			if ( $ajax ) {
				for ( $i = 1; $i <= $pages; $i ++ ) {
					$html .= ( $paged == $i ) ? '<li class="active"><span>' . $i . '</span></li>' : "<li><a data-paged='{$i}' href='" . get_pagenum_link( $i ) . "'>" . $i . '</a></li>';
				}
			} else {
				for ( $i = 1; $i <= $pages; $i ++ ) {
					if ( 1 != $pages && ( ! ( $i >= $paged + $range + 1 || $i <= $paged - $range - 1 ) || $pages <= $showitems ) ) {
						$html .= ( $paged == $i ) ? '<li class="active"><span>' . $i . '</span></li>' : "<li><a data-paged='{$i}' href='" . get_pagenum_link( $i ) . "'>" . $i . '</a></li>';
					}
				}
			}

			if ( $paged < $pages && $showitems < $pages && ! $ajax ) {
				$p    = $paged + 1;
				$html .= "<li><a data-paged='{$p}' href=\"" . get_pagenum_link( $paged + 1 ) . "\"  aria-label='Next'>&rsaquo;</a></li>";
			}

			if ( $paged < $pages - 1 && $paged + $range - 1 < $pages && $showitems < $pages && ! $ajax ) {
				$html .= "<li><a data-paged='{$pages}' href='" . get_pagenum_link( $pages ) . "' aria-label='Last'>&raquo;</a></li>";
			}

			$html .= '</ul>';
			$html .= '</div>';
		}

		return $html;
	}

	public static function rt_pagination_ajax( $scID, $range = 4, $pages = '' ) {
		$html = null;

		$html .= "<div class='rt-dowp-pagination-ajax' data-sc-id='{$scID}' data-paged='1'>";
		$html .= '</div>';

		return $html;
	}

	/**
	 * Call the Image resize model for resize function
	 *
	 * @param              $url
	 * @param null $width
	 * @param null $height
	 * @param null $crop
	 * @param bool|true $single
	 * @param bool|false $upscale
	 *
	 * @return array|bool|string
	 * @throws Exception
	 * @throws Rt_Exception
	 */
	public static function rtImageReSize( $url, $width = null, $height = null, $crop = null, $single = true, $upscale = false ) {
		$rtResize = new ReSizer();

		return $rtResize->process( $url, $width, $height, $crop, $single, $upscale );
	}


	/* Convert hexdec color string to rgb(a) string */
	public static function rtHex2rgba( $color, $opacity = .5 ) {
		$default = 'rgb(0,0,0)';

		// Return default if no color provided.
		if ( empty( $color ) ) {
			return $default;
		}

		// Sanitize $color if "#" is provided.
		if ( $color[0] == '#' ) {
			$color = substr( $color, 1 );
		}

		// Check if color has 6 or 3 characters and get values.
		if ( strlen( $color ) == 6 ) {
			$hex = [ $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] ];
		} else if ( strlen( $color ) == 3 ) {
			$hex = [ $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] ];
		} else {
			return $default;
		}

		// Convert hexadec to rgb.
		$rgb = array_map( 'hexdec', $hex );

		// Check if opacity is set(rgba or rgb).
		if ( $opacity ) {
			if ( absint( $opacity ) > 1 ) {
				$opacity = 1.0;
			}

			$output = 'rgba(' . implode( ',', $rgb ) . ',' . $opacity . ')';
		} else {
			$output = 'rgb(' . implode( ',', $rgb ) . ')';
		}

		// Return rgb(a) color string.
		return $output;
	}

	public static function meta_exist( $meta_key, $post_id = null, $type = 'post' ) {
		if ( ! $post_id ) {
			return false;
		}

		return metadata_exists( $type, $post_id, $meta_key );
	}


	public static function get_offset_col( $col ) {
		$return = [
			'big'   => 6,
			'small' => 6,
		];

		if ( $col ) {
			if ( $col == 12 ) {
				$return['big']   = 12;
				$return['small'] = 12;
			} else if ( $col == 6 ) {
				$return['big']   = 6;
				$return['small'] = 6;
			} else if ( $col == 4 ) {
				$return['big']   = 4;
				$return['small'] = 8;
			}
		}

		return $return;
	}

	public static function formatSpacing( $data = '' ) {
		if ( ! empty( $data ) ) {
			$spacing = array_filter( explode( ',', $data ), 'is_numeric' );

			if ( count( $spacing ) > 4 ) {
				$spacing = array_slice( $spacing, 0, 4, true );
			}

			$data = implode( 'px ', $spacing );
		}

		return $data;
	}

	public static function layoutStyle( $layoutID, $scMeta, $layout, $scId = null ) {
		$css = null;
		$css .= "<style type='text/css' media='all'>";
		// primary color
		if ( $scId ) {
			$primaryColor                   = ( isset( $scMeta['primary_color'][0] ) ? $scMeta['primary_color'][0] : null );
			$button_bg_color                = ( isset( $scMeta['button_bg_color'][0] ) ? $scMeta['button_bg_color'][0] : null );
			$button_active_bg_color         = ( isset( $scMeta['button_active_bg_color'][0] ) ? $scMeta['button_active_bg_color'][0] : null );
			$button_hover_bg_color          = ( isset( $scMeta['button_hover_bg_color'][0] ) ? $scMeta['button_hover_bg_color'][0] : null );
			$button_text_color              = ( isset( $scMeta['button_text_bg_color'][0] ) ? $scMeta['button_text_bg_color'][0]
				: ( isset( $scMeta['button_text_color'][0] ) ? $scMeta['button_text_color'][0] : null ) );
			$button_hover_text_color        = ( isset( $scMeta['button_hover_text_color'][0] ) ? $scMeta['button_hover_text_color'][0] : null );
			$button_border_color            = ( isset( $scMeta['button_border_color'][0] ) ? $scMeta['button_border_color'][0] : null );
			$overlay_color                  = ( ! empty( $scMeta['overlay_color'][0] ) ? self::rtHex2rgba(
				$scMeta['overlay_color'][0],
				! empty( $scMeta['overlay_opacity'][0] ) ? absint( $scMeta['overlay_opacity'][0] ) / 10 : .8
			) : null );
			$overlay_padding                = ( ! empty( $scMeta['overlay_padding'][0] ) ? absint( $scMeta['overlay_padding'][0] ) : null );
			$gutter                         = ! empty( $scMeta['tgp_gutter'][0] ) ? absint( $scMeta['tgp_gutter'][0] ) : null;
			$read_more_button_border_radius = isset( $scMeta['tpg_read_more_button_border_radius'][0] ) ? $scMeta['tpg_read_more_button_border_radius'][0] : '';
			// Section
			$sectionBg      = ( isset( $scMeta['tpg_full_area_bg'][0] ) ? $scMeta['tpg_full_area_bg'][0] : null );
			$sectionMargin  = ( isset( $scMeta['tpg_full_area_margin'][0] ) ? $scMeta['tpg_full_area_margin'][0] : null );
			$sectionMargin  = self::formatSpacing( $sectionMargin );
			$sectionPadding = ( isset( $scMeta['tpg_full_area_padding'][0] ) ? $scMeta['tpg_full_area_padding'][0] : null );
			$sectionPadding = self::formatSpacing( $sectionPadding );
			// Box
			$boxBg           = ( isset( $scMeta['tpg_content_wrap_bg'][0] ) ? $scMeta['tpg_content_wrap_bg'][0] : null );
			$boxBorder       = ( isset( $scMeta['tpg_content_wrap_border'][0] ) ? $scMeta['tpg_content_wrap_border'][0] : null );
			$boxBorderColor  = ( isset( $scMeta['tpg_content_wrap_border_color'][0] ) ? $scMeta['tpg_content_wrap_border_color'][0] : null );
			$boxBorderRadius = ( isset( $scMeta['tpg_content_wrap_border_radius'][0] ) ? $scMeta['tpg_content_wrap_border_radius'][0] : null );
			$boxShadow       = ( isset( $scMeta['tpg_content_wrap_shadow'][0] ) ? $scMeta['tpg_content_wrap_shadow'][0] : null );
			$boxPadding      = ( isset( $scMeta['tpg_box_padding'][0] ) ? $scMeta['tpg_box_padding'][0] : null );
			$boxPadding      = self::formatSpacing( $boxPadding );
			$contentPadding  = ( isset( $scMeta['tpg_content_padding'][0] ) ? $scMeta['tpg_content_padding'][0] : null );
			$contentPadding  = self::formatSpacing( $contentPadding );
			// Heading
			$headingBg          = ( isset( $scMeta['tpg_heading_bg'][0] ) ? $scMeta['tpg_heading_bg'][0] : null );
			$headingColor       = ( isset( $scMeta['tpg_heading_color'][0] ) ? $scMeta['tpg_heading_color'][0] : null );
			$headingBorderColor = ( isset( $scMeta['tpg_heading_border_color'][0] ) ? $scMeta['tpg_heading_border_color'][0] : null );
			$headingBorderSize  = ( isset( $scMeta['tpg_heading_border_size'][0] ) ? $scMeta['tpg_heading_border_size'][0] : null );
			$headingMargin      = ( isset( $scMeta['tpg_heading_margin'][0] ) ? $scMeta['tpg_heading_margin'][0] : null );
			$headingMargin      = self::formatSpacing( $headingMargin );
			$headingPadding     = ( isset( $scMeta['tpg_heading_padding'][0] ) ? $scMeta['tpg_heading_padding'][0] : null );
			$headingPadding     = self::formatSpacing( $headingPadding );
			// Category
			$catBg           = ( isset( $scMeta['tpg_category_bg'][0] ) ? $scMeta['tpg_category_bg'][0] : null );
			$catTextColor    = ( isset( $scMeta['tpg_category_color'][0] ) ? $scMeta['tpg_category_color'][0] : null );
			$catBorderRadius = ( isset( $scMeta['tpg_category_border_radius'][0] ) ? $scMeta['tpg_category_border_radius'][0] : null );
			$catMargin       = ( isset( $scMeta['tpg_category_margin'][0] ) ? $scMeta['tpg_category_margin'][0] : null );
			$catMargin       = self::formatSpacing( $catMargin );
			$catPadding      = ( isset( $scMeta['tpg_category_padding'][0] ) ? $scMeta['tpg_category_padding'][0] : null );
			$catPadding      = self::formatSpacing( $catPadding );
			$categorySize    = ( ! empty( $scMeta['rt_tpg_category_font_size'][0] ) ? absint( $scMeta['rt_tpg_category_font_size'][0] ) : null );
			// Image
			$image_border_radius = isset( $scMeta['tpg_image_border_radius'][0] ) ? $scMeta['tpg_image_border_radius'][0] : '';
			// Title
			$title_color     = ( ! empty( $scMeta['title_color'][0] ) ? $scMeta['title_color'][0] : null );
			$title_size      = ( ! empty( $scMeta['title_size'][0] ) ? absint( $scMeta['title_size'][0] ) : null );
			$title_weight    = ( ! empty( $scMeta['title_weight'][0] ) ? $scMeta['title_weight'][0] : null );
			$title_alignment = ( ! empty( $scMeta['title_alignment'][0] ) ? $scMeta['title_alignment'][0] : null );

			$title_hover_color = ( ! empty( $scMeta['title_hover_color'][0] ) ? $scMeta['title_hover_color'][0] : null );

			$excerpt_color     = ( ! empty( $scMeta['excerpt_color'][0] ) ? $scMeta['excerpt_color'][0] : null );
			$excerpt_size      = ( ! empty( $scMeta['excerpt_size'][0] ) ? absint( $scMeta['excerpt_size'][0] ) : null );
			$excerpt_weight    = ( ! empty( $scMeta['excerpt_weight'][0] ) ? $scMeta['excerpt_weight'][0] : null );
			$excerpt_alignment = ( ! empty( $scMeta['excerpt_alignment'][0] ) ? $scMeta['excerpt_alignment'][0] : null );

			$meta_data_color     = ( ! empty( $scMeta['meta_data_color'][0] ) ? $scMeta['meta_data_color'][0] : null );
			$meta_data_size      = ( ! empty( $scMeta['meta_data_size'][0] ) ? absint( $scMeta['meta_data_size'][0] ) : null );
			$meta_data_weight    = ( ! empty( $scMeta['meta_data_weight'][0] ) ? $scMeta['meta_data_weight'][0] : null );
			$meta_data_alignment = ( ! empty( $scMeta['meta_data_alignment'][0] ) ? $scMeta['meta_data_alignment'][0] : null );
		} else {
			$primaryColor                   = ( isset( $scMeta['primary_color'] ) ? $scMeta['primary_color'] : null );
			$button_bg_color                = ( isset( $scMeta['button_bg_color'] ) ? $scMeta['button_bg_color'] : null );
			$button_active_bg_color         = ( isset( $scMeta['button_active_bg_color'] ) ? $scMeta['button_active_bg_color'] : null );
			$button_hover_bg_color          = ( isset( $scMeta['button_hover_bg_color'] ) ? $scMeta['button_hover_bg_color'] : null );
			$btn_text_color                 = ( isset( $scMeta['button_text_color'] ) ? $scMeta['button_text_color'] : null );
			$button_text_color              = ( ! empty( $scMeta['button_text_bg_color'] ) ? $scMeta['button_text_bg_color']
				: ( ! empty( $btn_text_color ) ? $btn_text_color : null ) );
			$button_border_color            = ( isset( $scMeta['button_border_color'] ) ? $scMeta['button_border_color'] : null );
			$button_hover_text_color        = ( isset( $scMeta['button_hover_text_color'] ) ? $scMeta['button_hover_text_color'] : null );
			$overlay_color                  = ( ! empty( $scMeta['overlay_color'] ) ? self::rtHex2rgba(
				$scMeta['overlay_color'],
				! empty( $scMeta['overlay_opacity'] ) ? absint( $scMeta['overlay_opacity'] ) / 10 : .8
			) : null );
			$overlay_padding                = ( ! empty( $scMeta['overlay_padding'] ) ? absint( $scMeta['overlay_padding'] ) : null );
			$gutter                         = ! empty( $scMeta['tgp_gutter'] ) ? absint( $scMeta['tgp_gutter'] ) : null;
			$read_more_button_border_radius = isset( $scMeta['tpg_read_more_button_border_radius'] ) ? $scMeta['tpg_read_more_button_border_radius'] : '';
			// Section
			$sectionBg      = ( isset( $scMeta['tpg_full_area_bg'] ) ? $scMeta['tpg_full_area_bg'] : null );
			$sectionMargin  = ( isset( $scMeta['tpg_full_area_margin'] ) ? $scMeta['tpg_full_area_margin'] : null );
			$sectionMargin  = self::formatSpacing( $sectionMargin );
			$sectionPadding = ( isset( $scMeta['tpg_full_area_padding'] ) ? $scMeta['tpg_full_area_padding'] : null );
			$sectionPadding = self::formatSpacing( $sectionPadding );
			// Box
			$boxBg           = ( isset( $scMeta['tpg_content_wrap_bg'] ) ? $scMeta['tpg_content_wrap_bg'] : null );
			$boxBorder       = ( isset( $scMeta['tpg_content_wrap_border'] ) ? $scMeta['tpg_content_wrap_border'] : null );
			$boxBorderColor  = ( isset( $scMeta['tpg_content_wrap_border_color'] ) ? $scMeta['tpg_content_wrap_border_color'] : null );
			$boxBorderRadius = ( isset( $scMeta['tpg_content_wrap_border_radius'] ) ? $scMeta['tpg_content_wrap_border_radius'] : null );
			$boxShadow       = ( isset( $scMeta['tpg_content_wrap_shadow'] ) ? $scMeta['tpg_content_wrap_shadow'] : null );
			$boxPadding      = ( isset( $scMeta['tpg_box_padding'] ) ? $scMeta['tpg_box_padding'] : null );
			$boxPadding      = self::formatSpacing( $boxPadding );
			$contentPadding  = ( isset( $scMeta['tpg_content_padding'] ) ? $scMeta['tpg_content_padding'] : null );
			$contentPadding  = self::formatSpacing( $contentPadding );
			// Heading
			$headingBg          = ( isset( $scMeta['tpg_heading_bg'] ) ? $scMeta['tpg_heading_bg'] : null );
			$headingColor       = ( isset( $scMeta['tpg_heading_color'] ) ? $scMeta['tpg_heading_color'] : null );
			$headingBorderColor = ( isset( $scMeta['tpg_heading_border_color'] ) ? $scMeta['tpg_heading_border_color'] : null );
			$headingBorderSize  = ( isset( $scMeta['tpg_heading_border_size'] ) ? $scMeta['tpg_heading_border_size'] : null );
			$headingMargin      = ( isset( $scMeta['tpg_heading_margin'] ) ? $scMeta['tpg_heading_margin'] : null );
			$headingMargin      = self::formatSpacing( $headingMargin );
			$headingPadding     = ( isset( $scMeta['tpg_heading_padding'] ) ? $scMeta['tpg_heading_padding'] : null );
			$headingPadding     = self::formatSpacing( $headingPadding );
			// Category
			$catBg           = ( isset( $scMeta['tpg_category_bg'] ) ? $scMeta['tpg_category_bg'] : null );
			$catTextColor    = ( isset( $scMeta['tpg_category_color'] ) ? $scMeta['tpg_category_color'] : null );
			$catBorderRadius = ( isset( $scMeta['tpg_category_border_radius'] ) ? $scMeta['tpg_category_border_radius'] : null );
			$catMargin       = ( isset( $scMeta['tpg_category_margin'] ) ? $scMeta['tpg_category_margin'] : null );
			$catPadding      = ( isset( $scMeta['tpg_category_padding'] ) ? $scMeta['tpg_category_padding'] : null );
			$categorySize    = ( ! empty( $scMeta['rt_tpg_category_font_size'] ) ? absint( $scMeta['rt_tpg_category_font_size'] ) : null );
			// Image
			$image_border_radius = isset( $scMeta['tpg_image_border_radius'] ) ? $scMeta['tpg_image_border_radius'] : '';
			// Title
			$title_color     = ( ! empty( $scMeta['title_color'] ) ? $scMeta['title_color'] : null );
			$title_size      = ( ! empty( $scMeta['title_size'] ) ? absint( $scMeta['title_size'] ) : null );
			$title_weight    = ( ! empty( $scMeta['title_weight'] ) ? $scMeta['title_weight'] : null );
			$title_alignment = ( ! empty( $scMeta['title_alignment'] ) ? $scMeta['title_alignment'] : null );

			$title_hover_color = ( ! empty( $scMeta['title_hover_color'] ) ? $scMeta['title_hover_color'] : null );

			$excerpt_color     = ( ! empty( $scMeta['excerpt_color'] ) ? $scMeta['excerpt_color'] : null );
			$excerpt_size      = ( ! empty( $scMeta['excerpt_size'] ) ? absint( $scMeta['excerpt_size'] ) : null );
			$excerpt_weight    = ( ! empty( $scMeta['excerpt_weight'] ) ? $scMeta['excerpt_weight'] : null );
			$excerpt_alignment = ( ! empty( $scMeta['excerpt_alignment'] ) ? $scMeta['excerpt_alignment'] : null );

			$meta_data_color     = ( ! empty( $scMeta['meta_data_color'] ) ? $scMeta['meta_data_color'] : null );
			$meta_data_size      = ( ! empty( $scMeta['meta_data_size'] ) ? absint( $scMeta['meta_data_size'] ) : null );
			$meta_data_weight    = ( ! empty( $scMeta['meta_data_weight'] ) ? $scMeta['meta_data_weight'] : null );
			$meta_data_alignment = ( ! empty( $scMeta['meta_data_alignment'] ) ? $scMeta['meta_data_alignment'] : null );
		}

		$id = str_replace( 'rt-dowp-container-', '', $layoutID );

		if ( $primaryColor ) {
			$css .= "#{$layoutID} .rt-holder .rt-woo-info .price{";
			$css .= 'color:' . $primaryColor . ';';
			$css .= '}';
			$css .= "body .rt-dowp-container .rt-dowp-isotope-buttons .selected,
					#{$layoutID} .layout12 .rt-holder:hover .rt-detail,
					#{$layoutID} .isotope8 .rt-holder:hover .rt-detail,
					#{$layoutID} .carousel8 .rt-holder:hover .rt-detail,
					#{$layoutID} .layout13 .rt-holder .overlay .post-info,
					#{$layoutID} .isotope9 .rt-holder .overlay .post-info,
					#{$layoutID}.rt-dowp-container .layout4 .rt-holder .rt-detail,
					.rt-modal-{$id} .md-content,
					.rt-modal-{$id} .md-content > .rt-md-content-holder .rt-md-content,
					.rt-popup-wrap-{$id}.rt-popup-wrap .rt-popup-navigation-wrap,
					#{$layoutID} .carousel9 .rt-holder .overlay .post-info{";
			$css .= 'background-color:' . $primaryColor . ';';
			$css .= '}';

			$ocp = self::rtHex2rgba(
				$primaryColor,
				! empty( $scMeta['overlay_opacity'][0] ) ? absint( $scMeta['overlay_opacity'][0] ) / 10 : .8
			);
			$css .= "#{$layoutID} .layout5 .rt-holder .overlay, #{$layoutID} .isotope2 .rt-holder .overlay, #{$layoutID} .carousel2 .rt-holder .overlay,#{$layoutID} .layout15 .rt-holder h3, #{$layoutID} .isotope11 .rt-holder h3, #{$layoutID} .carousel11 .rt-holder h3, #{$layoutID} .layout16 .rt-holder h3,
					#{$layoutID} .isotope12 .rt-holder h3, #{$layoutID} .carousel12 .rt-holder h3 {";
			$css .= 'background-color:' . $ocp . ';';
			$css .= '}';
		}

		if ( $button_border_color ) {
			$css .= "#{$layoutID} .rt-filter-item-wrap.rt-filter-button-wrap span.rt-filter-button-item,
					#{$layoutID} .rt-layout-filter-container .rt-filter-sub-tax.sub-button-group .rt-filter-button-item,
					#{$layoutID}.rt-dowp-container .swiper-navigation .slider-btn,
					#{$layoutID} .rt-layout-filter-container .rt-filter-wrap .rt-filter-item-wrap.rt-sort-order-action,
					#{$layoutID} .rt-layout-filter-container .rt-filter-wrap .rt-filter-item-wrap.rt-filter-dropdown-wrap .rt-filter-dropdown .rt-filter-dropdown-item,
					#{$layoutID} .rt-layout-filter-container .rt-filter-wrap .rt-filter-item-wrap.rt-filter-dropdown-wrap{";
			$css .= 'border-color:' . $button_border_color . ' !important;';
			$css .= '}';
			$css .= "#{$layoutID} .rt-holder .read-more a {";
			$css .= 'border-color:' . $button_border_color . ';';
			$css .= '}';
		}

		if ( $button_bg_color ) {
			$css .= "#{$layoutID} .pagination-list li a,
					{$layoutID} .pagination-list li span,
					{$layoutID} .pagination li a,
					#{$layoutID} .rt-dowp-isotope-buttons button,
					#{$layoutID} .rt-dowp-utility .rt-dowp-load-more button,
					#{$layoutID}.rt-dowp-container .swiper-navigation .slider-btn,
					#{$layoutID}.rt-dowp-container .swiper-pagination-bullet,
					#{$layoutID} .wc1 .rt-holder .rt-img-holder .overlay .product-more ul li a,
					#{$layoutID} .wc2 .rt-detail .rt-wc-add-to-cart,
					#{$layoutID} .wc3 .rt-detail .rt-wc-add-to-cart,
					#{$layoutID} .wc4 .rt-detail .rt-wc-add-to-cart,
					#{$layoutID} .wc-carousel2 .rt-detail .rt-wc-add-to-cart,
					#{$layoutID} .wc-isotope2 .rt-detail .rt-wc-add-to-cart,
					#{$layoutID} .rt-layout-filter-container .rt-filter-wrap .rt-filter-item-wrap.rt-filter-dropdown-wrap .rt-filter-dropdown,
					#{$layoutID} .rt-layout-filter-container .rt-filter-sub-tax.sub-button-group .rt-filter-button-item,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap .rt-page-numbers .paginationjs .paginationjs-pages ul li>a,
					#{$layoutID} .rt-filter-item-wrap.rt-filter-button-wrap span.rt-filter-button-item,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap  .rt-loadmore-btn,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap .rt-cb-page-prev-next > *,
					#{$layoutID} .rt-read-more,
					#rt-tooltip-{$id}, #rt-tooltip-{$id} .rt-tooltip-bottom:after{";
			$css .= 'background-color:' . $button_bg_color . ';';
			$css .= '}';
			$css .= "#{$layoutID} .rt-filter-item-wrap.rt-filter-button-wrap span.rt-filter-button-item,
					#{$layoutID} .rt-layout-filter-container .rt-filter-sub-tax.sub-button-group .rt-filter-button-item{";
			$css .= 'border-color:' . $button_bg_color . ';';
			$css .= '}';
			$css .= "#{$layoutID}.rt-dowp-container .layout17 .rt-holder .overlay a.dowp-zoom .fa{";
			$css .= 'color:' . $button_bg_color . ';';
			$css .= '}';

			$css .= "#{$layoutID} .rt-holder .read-more a {";
			$css .= 'background-color:' . $button_bg_color . ';padding: 8px 15px;';
			$css .= '}';
		}

		// button active color.
		if ( $button_active_bg_color ) {
			$css .= "#{$layoutID} .pagination li.active span,
					#{$layoutID} .pagination-list li.active span,
					#{$layoutID} .rt-dowp-isotope-buttons button.selected,
					#{$layoutID} .rt-filter-item-wrap.rt-filter-button-wrap span.rt-filter-button-item.selected,
					#{$layoutID} .rt-layout-filter-container .rt-filter-sub-tax.sub-button-group .rt-filter-button-item.selected,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap .rt-page-numbers .paginationjs .paginationjs-pages ul li.active>a,
					#{$layoutID}.rt-dowp-container .swiper-pagination-bullet.swiper-pagination-bullet-active-main{";
			$css .= 'background-color:' . $button_active_bg_color . ';';
			$css .= '}';

			$css .= "#{$layoutID} .rt-filter-item-wrap.rt-filter-button-wrap span.rt-filter-button-item.selected,
					#{$layoutID} .rt-layout-filter-container .rt-filter-sub-tax.sub-button-group .rt-filter-button-item.selected,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap .rt-page-numbers .paginationjs .paginationjs-pages ul li.active>a{";
			$css .= 'border-color:' . $button_active_bg_color . ';';
			$css .= '}';
		}

		// Button hover bg color.
		if ( $button_hover_bg_color ) {
			$css .= "#{$layoutID} .pagination-list li a:hover,
					#{$layoutID} .pagination li a:hover,
					#{$layoutID} .rt-dowp-isotope-buttons button:hover,
					#{$layoutID} .rt-holder .read-more a:hover,
					#{$layoutID} .rt-dowp-utility .rt-dowp-load-more button:hover,
					#{$layoutID}.rt-dowp-container .swiper-pagination-bullet:hover,
					#{$layoutID}.rt-dowp-container .swiper-navigation .slider-btn:hover,
					#{$layoutID} .wc1 .rt-holder .rt-img-holder .overlay .product-more ul li a:hover,
					#{$layoutID} .wc2 .rt-detail .rt-wc-add-to-cart:hover,
					#{$layoutID} .wc3 .rt-detail .rt-wc-add-to-cart:hover,
					#{$layoutID} .wc4 .rt-detail .rt-wc-add-to-cart:hover,
					#{$layoutID} .wc-carousel2 .rt-detail .rt-wc-add-to-cart:hover,
					#{$layoutID} .wc-isotope2 .rt-detail .rt-wc-add-to-cart:hover,
					#{$layoutID} .rt-layout-filter-container .rt-filter-wrap .rt-filter-item-wrap.rt-filter-dropdown-wrap .rt-filter-dropdown .rt-filter-dropdown-item:hover,
					#{$layoutID} .rt-layout-filter-container .rt-filter-wrap .rt-filter-item-wrap.rt-filter-dropdown-wrap .rt-filter-dropdown .rt-filter-dropdown-item.selected,
					#{$layoutID} .rt-filter-item-wrap.rt-filter-button-wrap span.rt-filter-button-item:hover,
					#{$layoutID} .rt-layout-filter-container .rt-filter-sub-tax.sub-button-group .rt-filter-button-item:hover,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap .rt-page-numbers .paginationjs .paginationjs-pages ul li>a:hover,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap .rt-cb-page-prev-next > *:hover,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap  .rt-loadmore-btn:hover,
					#{$layoutID} .rt-read-more:hover,
					#{$layoutID} .rt-dowp-utility .rt-dowp-load-more button:hover{";
			$css .= 'background-color:' . $button_hover_bg_color . ';';
			$css .= '}';

			$css .= "#{$layoutID} .rt-filter-item-wrap.rt-filter-button-wrap span.rt-filter-button-item:hover,
						#{$layoutID} .rt-layout-filter-container .rt-filter-sub-tax.sub-button-group .rt-filter-button-item:hover,
						#{$layoutID}.rt-dowp-container .swiper-navigation .slider-btn:hover,
						#{$layoutID}.rt-dowp-container .rt-pagination-wrap .rt-page-numbers .paginationjs .paginationjs-pages ul li>a:hover{";
			$css .= 'border-color:' . $button_hover_bg_color . ';';
			$css .= '}';
			$css .= "#{$layoutID}.rt-dowp-container .layout17 .rt-holder .overlay a.dowp-zoom:hover .fa{";
			$css .= 'color:' . $button_hover_bg_color . ';';
			$css .= '}';
		}

		// Button text color.
		if ( $button_text_color ) {
			$css .= "#{$layoutID} .pagination-list li a,
					#{$layoutID} .pagination li a,
					#{$layoutID} .rt-dowp-isotope-buttons button,
					#{$layoutID} .rt-holder .read-more a,
					#{$layoutID} .rt-dowp-utility .rt-dowp-load-more button,
					#{$layoutID}.rt-dowp-container .swiper-navigation .slider-btn,
					#{$layoutID} .wc1 .rt-holder .rt-img-holder .overlay .product-more ul li a,
					#{$layoutID} .edd1 .rt-holder .rt-img-holder .overlay .product-more ul li a,
					#{$layoutID} .wc2 .rt-detail .rt-wc-add-to-cart,
					#{$layoutID} .wc3 .rt-detail .rt-wc-add-to-cart,
					#{$layoutID} .edd2 .rt-detail .rt-wc-add-to-cart,
					#{$layoutID} .wc4 .rt-detail .rt-wc-add-to-cart,
					#{$layoutID} .edd3 .rt-detail .rt-wc-add-to-cart,
					#{$layoutID} .wc-carousel2 .rt-detail .rt-wc-add-to-cart,
					#{$layoutID} .wc-isotope2 .rt-detail .rt-wc-add-to-cart,
					#{$layoutID} .rt-dowp-utility .rt-dowp-load-more button,
					#rt-tooltip-{$id},
					#{$layoutID} .rt-filter-item-wrap.rt-filter-button-wrap span.rt-filter-button-item,
					#{$layoutID} .rt-layout-filter-container .rt-filter-sub-tax.sub-button-group .rt-filter-button-item,
					#{$layoutID} .rt-layout-filter-container .rt-filter-wrap .rt-filter-item-wrap.rt-sort-order-action,
					#{$layoutID} .rt-layout-filter-container .rt-filter-wrap .rt-filter-item-wrap.rt-filter-dropdown-wrap .rt-filter-dropdown .rt-filter-dropdown-item,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap .rt-page-numbers .paginationjs .paginationjs-pages ul li>a,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap .rt-cb-page-prev-next > *,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap  .rt-loadmore-btn,
					#{$layoutID} .rt-read-more,
					#rt-tooltip-{$id} .rt-tooltip-bottom:after{";
			$css .= 'color:' . $button_text_color . ';';
			$css .= '}';
		}

		if ( $button_hover_text_color ) {
			$css .= "#{$layoutID} .rt-filter-item-wrap.rt-filter-button-wrap span.rt-filter-button-item:hover,
					#{$layoutID} .rt-holder .read-more a:hover,
					#{$layoutID}.rt-dowp-container .swiper-navigation .slider-btn:hover,
					#{$layoutID} .rt-layout-filter-container .rt-filter-sub-tax.sub-button-group .rt-filter-button-item:hover,
					#{$layoutID} .rt-layout-filter-container .rt-filter-wrap .rt-filter-item-wrap.rt-filter-dropdown-wrap .rt-filter-dropdown .rt-filter-dropdown-item:hover,
					#{$layoutID} .rt-layout-filter-container .rt-filter-wrap .rt-filter-item-wrap.rt-filter-dropdown-wrap .rt-filter-dropdown .rt-filter-dropdown-item.selected,
					#{$layoutID} .rt-layout-filter-container .rt-filter-wrap .rt-filter-item-wrap.rt-sort-order-action:hover,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap .rt-page-numbers .paginationjs .paginationjs-pages ul li.active>a:hover,
					#{$layoutID} .rt-filter-item-wrap.rt-filter-button-wrap span.rt-filter-button-item.selected,
					#{$layoutID} .rt-layout-filter-container .rt-filter-wrap .rt-filter-item-wrap.rt-sort-order-action,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap  .rt-loadmore-btn:hover,
					#{$layoutID} .rt-read-more:hover,
					#{$layoutID}.rt-dowp-container .rt-pagination-wrap .rt-page-numbers .paginationjs .paginationjs-pages ul li.active>a{";
			$css .= 'color:' . $button_hover_text_color . ';';
			$css .= '}';
		}

		if ( $overlay_color || $overlay_padding ) {
			if ( in_array( $layout, [ 'layout15', 'isotope11', 'carousel11' ] ) ) {
				$css .= "#{$layoutID} .{$layout} .rt-holder:hover .overlay .post-info{";
			} else if ( in_array(
				$layout,
				[ 'layout10', 'isotope7', 'carousel6', 'carousel7', 'layout9', 'offset04' ]
			)
			) {
				$css .= "#{$layoutID} .{$layout} .rt-holder .post-info{";
			} else if ( in_array( $layout, [ 'layout7', 'isotope4', 'carousel4' ] ) ) {
				$css .= "#{$layoutID} .{$layout} .rt-holder .overlay:hover{";
			} else if ( in_array( $layout, [ 'layout16', 'isotope12', 'carousel12' ] ) ) {
				$css .= "#{$layoutID} .{$layout} .rt-holder .overlay .post-info {";
			} else if ( in_array( $layout, [ 'offset03', 'carousel5' ] ) ) {
				$css .= "#{$layoutID} .{$layout} .rt-holder .overlay{";
			} else {
				$css .= "#{$layoutID} .rt-post-overlay .post-img > a:first-of-type::after,";
				$css .= "#{$layoutID} .rt-holder .overlay:hover{";
			}

			if ( $overlay_color ) {
				$css .= 'background-image: none;';
				$css .= 'background-color:' . $overlay_color . ';';
			}

			if ( $overlay_padding ) {
				$css .= 'padding-top:' . $overlay_padding . '%;';
			}

			$css .= '}';
		}

		if ( $boxShadow ) {
			$css .= "#{$layoutID} .{$layout} .rt-holder {";
			$css .= "box-shadow : 0px 0px 2px 0px {$boxShadow};";
			$css .= '}';
		}

		/* gutter */
		if ( $gutter ) {
			$css .= "#{$layoutID} [class*='rt-col-'] {";
			$css .= "padding-left : {$gutter}px !important;";
			$css .= "padding-right : {$gutter}px !important;";
			$css .= "margin-top : {$gutter}px;";
			$css .= "margin-bottom : {$gutter}px;";
			$css .= '}';
			$css .= "#{$layoutID} .rt-row{";
			$css .= "margin-left : -{$gutter}px !important;";
			$css .= "margin-right : -{$gutter}px !important;";
			$css .= '}';
			$css .= "#{$layoutID}.rt-container-fluid,#{$layoutID}.rt-container{";
			$css .= "padding-left : {$gutter}px;";
			$css .= "padding-right : {$gutter}px;";
			$css .= '}';

			// remove inner row margin.
			$css .= "#{$layoutID} .rt-row .rt-row [class*='rt-col-'] {";
			$css .= 'margin-top : 0;';
			$css .= '}';
		}

		// Read more button border radius.
		if ( isset( $read_more_button_border_radius ) || trim( $read_more_button_border_radius ) !== '' ) {
			$css .= "#{$layoutID} .read-more a{";
			$css .= 'border-radius:' . $read_more_button_border_radius . 'px;';
			$css .= '}';
		}

		// Section.
		if ( $sectionBg ) {
			$css .= "#{$layoutID}.rt-dowp-container {";
			$css .= 'background:' . $sectionBg . ';';
			$css .= '}';
		}

		if ( $sectionMargin ) {
			$css .= "#{$layoutID}.rt-dowp-container {";
			$css .= 'margin:' . $sectionMargin . 'px;';
			$css .= '}';
		}

		if ( $sectionPadding ) {
			$css .= "#{$layoutID}.rt-dowp-container {";
			$css .= 'padding:' . $sectionPadding . 'px;';
			$css .= '}';
		}

		// Box.
		if ( $boxBg ) {
			$css .= "#{$layoutID} .rt-holder, #{$layoutID} .rt-holder .rt-detail,#{$layoutID} .rt-post-overlay .post-img + .post-content {";
			$css .= 'background-color:' . $boxBg . ';';
			$css .= '}';
		}

		if ( $boxBorderColor ) {
			$css .= "#{$layoutID} .rt-holder {";
			$css .= 'border-color:' . $boxBorderColor . ';';
			$css .= '}';
		}

		if ( $boxBorder ) {
			$css .= "#{$layoutID} .rt-holder {";
			$css .= 'border-style: solid;';
			$css .= 'border-width:' . $boxBorder . 'px;';
			$css .= '}';
		}

		if ( $boxBorderRadius ) {
			$css .= "#{$layoutID} .rt-holder {";
			$css .= 'border-radius:' . $boxBorderRadius . 'px;';
			$css .= '}';
		}

		if ( $boxPadding ) {
			$css .= "#{$layoutID} .rt-holder {";
			$css .= 'padding:' . $boxPadding . 'px;';
			$css .= '}';
		}

		if ( $contentPadding ) {
			$css .= "#{$layoutID} .rt-holder .rt-detail {";
			$css .= 'padding:' . $contentPadding . 'px;';
			$css .= '}';
		}

		// Widget heading.
		if ( $headingBg ) {
			$css .= "#{$layoutID} .dowp-widget-heading-wrapper.heading-style1 .dowp-widget-heading, #{$layoutID} .dowp-widget-heading-wrapper.heading-style2 .dowp-widget-heading, #{$layoutID} .dowp-widget-heading-wrapper.heading-style3 .dowp-widget-heading {";
			$css .= 'background:' . $headingBg . ';';
			$css .= '}';

			$css .= "#{$layoutID} .dowp-widget-heading-wrapper.heading-style2 .dowp-widget-heading::after {";
			$css .= 'border-top-color:' . $headingBg . ';';
			$css .= '}';
		}

		if ( $headingColor ) {
			$css .= "#{$layoutID} .dowp-widget-heading-wrapper.heading-style1 .dowp-widget-heading, #{$layoutID} .dowp-widget-heading-wrapper.heading-style1 .dowp-widget-heading a, #{$layoutID} .dowp-widget-heading-wrapper.heading-style2 .dowp-widget-heading, #{$layoutID} .dowp-widget-heading-wrapper.heading-style2 .dowp-widget-heading a, #{$layoutID} .dowp-widget-heading-wrapper.heading-style3 .dowp-widget-heading, #{$layoutID} .dowp-widget-heading-wrapper.heading-style3 .dowp-widget-heading a  {";
			$css .= 'color:' . $headingColor . ';';
			$css .= '}';
			$css .= "#{$layoutID} .dowp-widget-heading-wrapper.heading-style1 .dowp-widget-heading::before  {";
			$css .= 'background-color:' . $headingColor . ';';
			$css .= '}';
		}

		if ( $headingBorderSize ) {
			$css .= "#{$layoutID} .dowp-widget-heading-wrapper.heading-style1, #{$layoutID} .dowp-widget-heading-wrapper.heading-style2, #{$layoutID} .dowp-widget-heading-wrapper.heading-style3 {";
			// $css .= "border-bottom-style: solid;";
			$css .= 'border-bottom-width:' . $headingBorderSize . 'px;';
			$css .= '}';

			$css .= "#{$layoutID} .dowp-widget-heading-wrapper.heading-style1 .dowp-widget-heading-line {";
			$css .= 'border-width:' . $headingBorderSize . 'px 0;';
			$css .= '}';
		}

		if ( $headingBorderColor ) {
			$css .= "#{$layoutID} .dowp-widget-heading-wrapper.heading-style1 .dowp-widget-heading-line, #{$layoutID} .dowp-widget-heading-wrapper.heading-style2, #{$layoutID} .dowp-widget-heading-wrapper.heading-style3  {";
			$css .= 'border-color:' . $headingBorderColor . ';';
			$css .= '}';
		}

		if ( $headingMargin ) {
			$css .= "#{$layoutID} .dowp-widget-heading-wrapper {";
			$css .= 'margin:' . $headingMargin . 'px;';
			$css .= '}';
		}

		if ( $headingPadding ) {
			$css .= "#{$layoutID} .dowp-widget-heading-wrapper .dowp-widget-heading {";
			$css .= 'padding:' . $headingPadding . 'px;';
			$css .= '}';
		}

		// Image border.
		if ( isset( $image_border_radius ) || trim( $image_border_radius ) !== '' ) {
			$css .= "#{$layoutID} .rt-img-holder img.rt-img-responsive,#{$layoutID} .rt-img-holder,
					#{$layoutID} .rt-post-overlay .post-img,
					#{$layoutID} .post-sm .post-img,
					#{$layoutID} .rt-post-grid .post-img,
					#{$layoutID} .post-img img {";
			$css .= 'border-radius:' . $image_border_radius . 'px;';
			$css .= '}';
		}

		// Title decoration.
		if ( $title_color || $title_size || $title_weight || $title_alignment ) {
			$css .= "#{$layoutID} .{$layout} .rt-holder h2.entry-title,
					#{$layoutID} .{$layout} .rt-holder h3.entry-title,
					#{$layoutID} .{$layout} .rt-holder h4.entry-title,
					#{$layoutID} .{$layout} .rt-holder h2.entry-title a,
					#{$layoutID} .{$layout} .rt-holder h3.entry-title a,
					#{$layoutID} .{$layout} .rt-holder h4.entry-title a,
					#{$layoutID} .rt-holder .rt-woo-info h2 a,
					#{$layoutID} .rt-holder .rt-woo-info h3 a,
					#{$layoutID} .rt-holder .rt-woo-info h4 a,
					#{$layoutID} .post-content .post-title,
					#{$layoutID} .rt-post-grid .post-title,
					#{$layoutID} .rt-post-grid .post-title a,
					#{$layoutID} .post-content .post-title a,
					#{$layoutID} .rt-holder .rt-woo-info h2,
					#{$layoutID} .rt-holder .rt-woo-info h3,
					#{$layoutID} .rt-holder .rt-woo-info h4{";

			if ( $title_color ) {
				$css .= 'color:' . $title_color . ';';
			}

			if ( $title_size ) {
				$lineHeight = $title_size + 10;
				$css        .= 'font-size:' . $title_size . 'px;';
				$css        .= 'line-height:' . $lineHeight . 'px;';
			}

			if ( $title_weight ) {
				$css .= 'font-weight:' . $title_weight . ';';
			}

			if ( $title_alignment ) {
				$css .= 'text-align:' . $title_alignment . ';';
			}

			$css .= '}';

			if ( $title_size ) {
				$css .= "#{$layoutID} .post-grid-lg-style-1 .post-title,
						#{$layoutID} .post-grid-lg-style-1 .post-title a,
						#{$layoutID} .big-layout .post-title,
						#{$layoutID} .big-layout .post-title a,
						#{$layoutID} .post-grid-lg-style-1 .post-title,
						#{$layoutID} .post-grid-lg-style-1 .post-title a {";
				$css .= 'font-size:' . ( $title_size + 8 ) . 'px;';
				$css .= 'line-height:' . ( $lineHeight + 8 ) . 'px;';
				$css .= '}';
			}
		}

		// Title hover color.
		if ( $title_hover_color ) {
			$css .= "#{$layoutID} .{$layout} .rt-holder h2.entry-title:hover,
					#{$layoutID} .{$layout} .rt-holder h3.entry-title:hover,
					#{$layoutID} .{$layout} .rt-holder h4.entry-title:hover,
					#{$layoutID} .{$layout} .rt-holder h2.entry-title a:hover,
					#{$layoutID} .{$layout} .rt-holder h3.entry-title a:hover,
					#{$layoutID} .{$layout} .rt-holder h4.entry-title a:hover,
					#{$layoutID} .post-content .post-title a:hover,
					#{$layoutID} .rt-post-grid .post-title a:hover,
					#{$layoutID} .rt-holder .rt-woo-info h2 a:hover,
					#{$layoutID} .rt-holder .rt-woo-info h3 a:hover,
					#{$layoutID} .rt-holder .rt-woo-info h4 a:hover,
					#{$layoutID} .rt-holder .rt-woo-info h2:hover,
					#{$layoutID} .rt-holder .rt-woo-info h3:hover,
					#{$layoutID} .rt-holder .rt-woo-info h4:hover{";
			$css .= 'color:' . $title_hover_color . ' !important;';
			$css .= '}';
		}
		// Excerpt decoration.
		if ( $excerpt_color || $excerpt_size || $excerpt_weight || $excerpt_alignment ) {
			$css .= "#{$layoutID} .{$layout} .rt-holder .dowp-excerpt,#{$layoutID} .{$layout} .dowp-excerpt,#{$layoutID} .{$layout} .rt-holder .post-content,#{$layoutID} .rt-holder .rt-woo-info p,#{$layoutID} .post-content p {";

			if ( $excerpt_color ) {
				$css .= 'color:' . $excerpt_color . ';';
			}

			if ( $excerpt_size ) {
				$css .= 'font-size:' . $excerpt_size . 'px;';
			}

			if ( $excerpt_weight ) {
				$css .= 'font-weight:' . $excerpt_weight . ';';
			}

			if ( $excerpt_alignment ) {
				$css .= 'text-align:' . $excerpt_alignment . ';';
			}

			$css .= '}';
		}

		// Post meta decoration.
		if ( $meta_data_color || $meta_data_size || $meta_data_weight || $meta_data_alignment ) {
			$css .= "#{$layoutID} .{$layout} .rt-holder .post-meta-user,
					#{$layoutID} .{$layout} .rt-meta,
					#{$layoutID} .{$layout} .rt-meta a,
					#{$layoutID} .{$layout} .rt-holder .post-meta-user .meta-data,
					#{$layoutID} .{$layout} .rt-holder .post-meta-user a,
					#{$layoutID} .{$layout} .rt-holder .rt-detail .post-meta .rt-dowp-social-share,
					#{$layoutID} .rt-post-overlay .post-meta-user span,
					#{$layoutID} .rt-post-overlay .post-meta-user,
					#{$layoutID} .rt-post-overlay .post-meta-user a,
					#{$layoutID} .rt-post-grid .post-meta-user,
					#{$layoutID} .rt-post-grid .post-meta-user a,
					#{$layoutID} .rt-post-box-media-style .post-meta-user,
					#{$layoutID} .rt-post-box-media-style .post-meta-user a,
					#{$layoutID} .{$layout} .post-meta-user i,
					#{$layoutID} .rt-detail .post-meta-category a,
					#{$layoutID} .{$layout} .post-meta-user a
					#{$layoutID} .{$layout} .post-meta-user a {";

			if ( $meta_data_color ) {
				$css .= 'color:' . $meta_data_color . ';';
			}

			if ( $meta_data_size ) {
				$css .= 'font-size:' . $meta_data_size . 'px;';
			}

			if ( $meta_data_weight ) {
				$css .= 'font-weight:' . $meta_data_weight . ';';
			}

			if ( $meta_data_alignment ) {
				$css .= 'text-align:' . $meta_data_alignment . ';';
			}

			$css .= '}';
		}

		// Category.
		if ( $catBg ) {
			$css .= "#{$layoutID} .cat-over-image.style2 .categories-links a,
					#{$layoutID} .cat-over-image.style3 .categories-links a,
					#{$layoutID} .cat-above-title.style2 .categories-links a,
					#{$layoutID} .cat-above-title.style3 .categories-links a,
					#{$layoutID} .rt-dowp-category > a {
						background-color: {$catBg};
					}";

			$css .= "#{$layoutID} .cat-above-title.style3 .categories-links a:after,
					.cat-over-image.style3 .categories-links a:after,
					#{$layoutID} .rt-dowp-category > a,
					#{$layoutID} .rt-dowp-category.style3 > a:after {
						border-top-color: {$catBg} ;
					}";

			$css .= "#{$layoutID} .rt-dowp-category:not(style1) i {
					color: {$catBg};
				}";
		}

		if ( $catTextColor ) {
			$css .= "#{$layoutID} .cat-over-image .categories-links a,
				#{$layoutID} .cat-above-title .categories-links a,
				#{$layoutID} .rt-dowp-category.style1 > i,
				#{$layoutID} .rt-dowp-category > a {";
			$css .= 'color:' . $catTextColor . ';';
			$css .= '}';
		}

		if ( $catBorderRadius ) {
			$css .= "#{$layoutID} .cat-over-image .categories-links a,#{$layoutID} .cat-above-title .categories-links a,#{$layoutID} .rt-dowp-category > a{";
			$css .= 'border-radius:' . $catBorderRadius . 'px;';
			$css .= '}';
		}

		if ( $catPadding ) {
			$css .= "#{$layoutID} .cat-over-image .categories-links a,#{$layoutID} .cat-above-title .categories-links a,#{$layoutID} .rt-dowp-category > a{";
			$css .= 'padding:' . $catPadding . 'px;';
			$css .= '}';
		}

		if ( $catMargin ) {
			$css .= "#{$layoutID} .categories-links,#{$layoutID} .rt-dowp-category > a{";
			$css .= 'margin:' . $catMargin . 'px;';
			$css .= '}';
		}

		if ( $categorySize ) {
			$css .= "#{$layoutID} .categories-links,#{$layoutID} .rt-dowp-category > a {";
			$css .= 'font-size:' . $categorySize . 'px;';
			$css .= '}';
		}

		$css .= '</style>';

		return $css;
	}

	public static function get_meta_keys( $post_type ) {
		$meta_keys = self::generate_meta_keys( $post_type );

		return $meta_keys;
	}

	public static function generate_meta_keys( $post_type ) {
		$meta_keys = [];

		if ( $post_type ) {
			global $wpdb;

			$query     = "SELECT DISTINCT($wpdb->postmeta.meta_key)
					FROM $wpdb->posts
					LEFT JOIN $wpdb->postmeta
					ON $wpdb->posts.ID = $wpdb->postmeta.post_id
					WHERE $wpdb->posts.post_type = '%s'
					AND $wpdb->postmeta.meta_key != ''
					AND $wpdb->postmeta.meta_key NOT RegExp '(^[_0-9].+$)'
					AND $wpdb->postmeta.meta_key NOT RegExp '(^[0-9]+$)'";
			$meta_keys = $wpdb->get_col( $wpdb->prepare( $query, $post_type ) );
		}

		return $meta_keys;
	}

	public static function remove_all_shortcode( $content ) {
		return preg_replace( '#\[[^\]]+\]#', '', $content );
	}

	public static function remove_divi_shortcodes( $content ) {
		$content = preg_replace( '/\[\/?et_pb.*?\]/', '', $content );

		return $content;
	}

	public static function is_acf() {
		$plugin = null;

		if ( class_exists( 'acf' ) ) {
			$plugin = 'acf';
		}

		return $plugin;
	}

	public static function is_woocommerce() {
		$plugin = null;
		if ( class_exists( 'WooCommerce' ) ) {
			$plugin = 'woo';
		}

		return $plugin;
	}

	public static function get_groups_by_post_type( $post_type ) {
		$post_type = $post_type ? $post_type : 'post';
		$groups    = [];
		$plugin    = self::is_acf();

		switch ( $plugin ) {
			case 'acf':
				$groups = self::get_groups_by_post_type_acf( $post_type );
				break;
		}

		return $groups;
	}

	/**
	 * Get ACF post group
	 *
	 * @param $post_type
	 *
	 * @return array
	 */
	public static function get_groups_by_post_type_acf( $post_type ) {
		$groups   = [];
		$groups_q = get_posts(
			[
				'post_type'      => 'acf-field-group',
				'posts_per_page' => - 1,
			]
		);

		if ( ! empty( $groups_q ) ) {
			foreach ( $groups_q as $group ) {
				$c    = $group->post_content ? unserialize( $group->post_content ) : [];
				$flag = false;

				if ( ! empty( $c['location'] ) ) {
					foreach ( $c['location'] as $rules ) {
						foreach ( $rules as $rule ) {
							if ( 'all' === $post_type ) {
								if ( ( ! empty( $rule['param'] ) && $rule['param'] == 'post_type' ) && ( ! empty( $rule['operator'] ) && $rule['operator'] == '==' )
								) {
									$flag = true;
								}
							} else {
								if ( ( ! empty( $rule['param'] ) && ( $rule['param'] == 'post_type' || ( $rule['param'] == 'post_category' && 'post' == $post_type ) ) ) && ( ! empty( $rule['operator'] ) && $rule['operator'] == '==' ) && ( ! empty( $rule['value'] ) && ( $rule['value'] == $post_type || ( $rule['param'] == 'post_category' && 'post' == $post_type ) ) )

								) {
									$flag = true;
								}
							}
						}
					}
				}
				if ( $flag ) {
					$groups[ $group->ID ] = $group->post_title;
				}
			}
		}

		return $groups;
	}

	/**
	 * Get Post view count meta key
	 *
	 * @return string
	 */
	public static function get_post_view_count_meta_key() {
		$count_key = 'dowp-post-view-count';

		return apply_filters( 'tpg_post_view_count', $count_key );
	}


	/**
	 * Elementor Functionality
	 * ************************************************
	 */


	/**
	 * Default layout style check
	 *
	 * @param $data
	 *
	 * @return bool
	 */
	public static function el_ignore_layout( $data ) {

		if ( isset( $data['category'] ) && 'category' == $data['category'] ) {
			return true;
		}
		$all_layout_list = [
			'grid-layout4',
			'grid-layout5',
			'grid-layout5-2',
			'grid-layout6',
			'grid-layout6-2',
			'list-layout4',
			'list-layout5',
			'grid_hover-layout5',
			'grid_hover-layout6',
			'grid_hover-layout7',
			'grid_hover-layout8',
			'grid_hover-layout9',
			'grid_hover-layout10',
			'grid_hover-layout5-2',
			'grid_hover-layout6-2',
			'grid_hover-layout7-2',
			'grid_hover-layout9-2',
			'grid_hover-layout11',
			'slider-layout3',
			'slider-layout5',
			'slider-layout6',
			'slider-layout7',
			'slider-layout8',
			'slider-layout9',
			'slider-layout11',
			'slider-layout12',
		];

		if ( 'default' == $data['category_position'] && in_array( $data['layout'], $all_layout_list ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Get Post Link
	 *
	 * @param $data
	 * @param $pID
	 *
	 * @return array
	 */
	public static function get_post_link( $pID, $data ) {
		$link_class    = $link_start = $link_end = $readmore_link_start = $readmore_link_end = null;
		$external_link = get_post_meta( $pID, 'tpg_read_more', true );

		if ( 'default' == $data['post_link_type'] ) {
			$link_class = 'dowp-post-link';
			$link_start = $readmore_link_start = sprintf(
				'<a data-id="%s" href="%s" class="%s" target="%s">',
				absint( $pID ),
				esc_url( $external_link['url'] ?? get_permalink() ),
				esc_attr( $link_class ),
				esc_attr( $external_link['target'] ?? $data['link_target'] )
			);
			$link_end   = $readmore_link_end = '</a>';
		} else if ( 'popup' == $data['post_link_type'] ) {
			$link_class = 'dowp-single-popup dowp-post-link';

			if ( did_action( 'elementor/loaded' ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				$link_class = 'dowp-post-link';
			}

			$link_start = $readmore_link_start = sprintf(
				'<a data-id="%s" href="%s" class="%s" target="%s">',
				absint( $pID ),
				esc_url( get_permalink() ),
				esc_attr( $link_class ),
				esc_attr( $data['link_target'] )
			);
			$link_end   = $readmore_link_end = '</a>';
		} else if ( 'multi_popup' == $data['post_link_type'] ) {
			$link_class = 'dowp-multi-popup dowp-post-link';
			$link_start = $readmore_link_start = sprintf(
				'<a data-id="%s" href="%s" class="%s" target="%s">',
				absint( $pID ),
				esc_url( get_permalink() ),
				esc_attr( $link_class ),
				esc_attr( $data['link_target'] )
			);
			$link_end   = $readmore_link_end = '</a>';
		} else {
			$link_class          = 'dowp-post-link';
			$readmore_link_start = sprintf(
				'<a data-id="%s" href="%s" class="%s" target="%s">',
				absint( $pID ),
				esc_url( get_permalink() ),
				esc_attr( $link_class ),
				esc_attr( $data['link_target'] )
			);
			$readmore_link_end   = '</a>';
		}

		return [
			'link_start'          => $link_start,
			'link_end'            => $link_end,
			'readmore_link_start' => $readmore_link_start,
			'readmore_link_end'   => $readmore_link_end,
		];
	}

	/**
	 * Get Post Type
	 *
	 * @return string[]|\WP_Post_Type[]
	 */
	public static function get_post_types() {
		$post_types = get_post_types(
			[
				'public'            => true,
			],
			'objects'
		);
		$post_types = wp_list_pluck( $post_types, 'label', 'name' );

		$exclude = [ 'attachment', 'revision', 'nav_menu_item', 'elementor_library', 'tpg_builder', 'e-landing-page' ];

		foreach ( $exclude as $ex ) {
			unset( $post_types[ $ex ] );
		}

		if ( ! dwpAIC()->hasPro() ) {
			$post_types = [
				'post' => $post_types['post'],
				'page' => $post_types['page'],
			];
		}

		return $post_types;
	}

	public static function rt_get_the_term_list( $post_id, $taxonomy, $before = '', $sep = '', $after = '' ) {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		if ( empty( $terms ) ) {
			return false;
		}

		$links = [];

		foreach ( $terms as $term ) {
			$meta_color      = get_term_meta( $term->term_id, 'dowp_category_color', true );
			$meta_color_code = $meta_color ? "--dowp-primary-color:#" . ltrim( $meta_color, '#' ) : '';

			$link = get_term_link( $term, $taxonomy );
			if ( is_wp_error( $link ) ) {
				return $link;
			}
			if ( dwpAIC()->hasPro() ) {
				$links[] = '<a class="' . $term->slug . '" style="' . esc_attr( $meta_color_code ) . '" href="' . esc_url( $link ) . '" rel="tag">' . $term->name . '</a>';
			} else {
				$links[] = '<a class="' . $term->slug . '" href="' . esc_url( $link ) . '" rel="tag">' . $term->name . '</a>';
			}
		}

		return $before . implode( $sep, $links ) . $after;
	}

	/**
	 * Get Post Meta HTML for Elementor
	 *
	 * @param $post_id
	 * @param $data
	 *
	 * @return string markup
	 */
	public static function get_post_meta_html( $post_id, $data, $echo = true ) {
		if ( $post_id ) {
			$post = get_post( $post_id );
		} else {
			global $post;
		}
		$author_id       = $post->post_author;
		$author_name     = get_the_author_meta( 'display_name', $post->post_author );
		$author          = apply_filters( 'dowp_author_link', sprintf( '<a href="%s">%s</a>', get_author_posts_url( $author_id ), $author_name ) );
		$comments_number = get_comments_number( $post_id );
		$comment_label   = '';

		if ( isset( $data['show_comment_count_label'] ) && $data['show_comment_count_label'] ) {
			$comment_label = $data['comment_count_label_singular'];

			if ( $comments_number > 1 ) {
				$comment_label = $data['comment_count_label_plural'];
			}
		}

		$comments_text = sprintf( '%s (%s)', esc_html( $comment_label ), number_format_i18n( $comments_number ) );
		$date          = get_the_date( '', $post );


		//Category and Tags Management
		$_cat_id            = isset( $data['post_type'] ) ? $data['post_type'] . '_taxonomy' : 'category';
		$_tag_id            = isset( $data['post_type'] ) ? $data['post_type'] . '_tags' : 'post_tag';
		$_category_id       = isset( $data[ $_cat_id ] ) ? $data[ $_cat_id ] : 'category';
		$_tag_id            = isset( $data[ $_tag_id ] ) ? $data[ $_tag_id ] : 'post_tag';
		$categories         = self::rt_get_the_term_list( $post_id, $_category_id, null, '<span class="rt-separator">,</span>' );
		$tags               = self::rt_get_the_term_list( $post_id, $_tag_id, null, '<span class="rt-separator">,</span>' );
		$count_key          = self::get_post_view_count_meta_key();
		$get_view_count     = get_post_meta( $post_id, $count_key, true );
		$meta_separator     = ( $data['meta_separator'] && 'default' !== $data['meta_separator'] ) ? sprintf( "<span class='separator'>%s</span>", $data['meta_separator'] ) : null;
		$category_condition = ( $categories && 'show' == $data['show_category'] );
		if ( ! isset( $data['is_guten_builder'] ) && dwpAIC()->hasPro() ) {
			$category_condition = ( $categories && 'show' == $data['show_category'] && self::el_ignore_layout( $data ) && in_array( $data['category_position'], [
					'default',
					'with_meta'
				] ) );
		}
		$post_meta_html = [];

		// Author Meta.
		ob_start();
		if ( 'show' === $data['show_author'] ) {
			$is_author_avatar = null;

			if ( 'icon' !== $data['show_author_image'] ) {
				$is_author_avatar = 'has-author-avatar';
			}
			?>
            <span class='author <?php echo esc_attr( $is_author_avatar ); ?>'>

				<?php
				if ( isset( $data['author_icon_visibility'] ) && $data['author_icon_visibility'] !== 'hide' ) {
					if ( 'icon' !== $data['show_author_image'] ) {
						echo get_avatar( $author_id, 80 );
					} else {
						if ( $data['show_meta_icon'] === 'yes' ) {
							if ( did_action( 'elementor/loaded' ) && isset( $data['user_icon']['value'] ) && $data['user_icon']['value'] ) {
								\Elementor\Icons_Manager::render_icon( $data['user_icon'], [ 'aria-hidden' => 'true' ] );
							} else {
								echo "<i class='" . Fns::change_icon( 'fa fa-user', 'user' ) . "'></i>";
							}
						}
					}
				}

				if ( $data['author_prefix'] ) {
					echo "<span class='author-prefix'>" . esc_html( $data['author_prefix'] ) . '</span>';
				}
				echo wp_kses( $author, self::allowedHtml() );
				?>
			</span>
			<?php
			echo wp_kses( $meta_separator, self::allowedHtml() );
		}

		$post_meta_html['author'] = ob_get_clean();


		// Category Meta.


		ob_start();
		if ( $category_condition ) {
			?>
            <span class='categories-links'>
				<?php
				if ( $data['show_meta_icon'] === 'yes' ) {
					if ( did_action( 'elementor/loaded' ) && isset( $data['cat_icon']['value'] ) && $data['cat_icon']['value'] ) {
						\Elementor\Icons_Manager::render_icon( $data['cat_icon'], [ 'aria-hidden' => 'true' ] );
					} else {
						echo "<i class='" . Fns::change_icon( 'fas fa-folder-open', 'folder' ) . "'></i>";
					}
				}
				echo wp_kses( $categories, self::allowedHtml() );
				?>

			</span>
			<?php
			echo wp_kses( $meta_separator, self::allowedHtml() );
		}
		$post_meta_html['category'] = ob_get_clean();

		ob_start();
		// Date Meta.
		if ( '' !== $data['show_date'] ) {
			$archive_year  = get_the_date( 'Y', $post );
			$archive_month = get_the_date( 'm', $post );
			$archive_day   = get_the_date( 'j', $post );
			?>
            <span class='date'>
				<?php
				if ( $data['show_meta_icon'] === 'yes' ) {
					if ( did_action( 'elementor/loaded' ) && isset( $data['date_icon']['value'] ) && $data['date_icon']['value'] ) {
						\Elementor\Icons_Manager::render_icon( $data['date_icon'], [ 'aria-hidden' => 'true' ] );
					} else {
						echo "<i class='" . Fns::change_icon( 'far fa-calendar-alt', 'calendar' ) . "'></i>";
					}
				}
				?>
				<a href="<?php echo esc_url( get_day_link( $archive_year, $archive_month, $archive_day ) ); ?>">
					<?php echo esc_html( $date ); ?>
				</a>
			</span>
			<?php
			echo wp_kses( $meta_separator, self::allowedHtml() );
		}

		$post_meta_html['date'] = ob_get_clean();

		ob_start();
		// Tags Meta.
		if ( $tags && 'show' == $data['show_tags'] ) {
			?>
            <span class='post-tags-links'>
				<?php
				if ( $data['show_meta_icon'] === 'yes' ) {
					if ( did_action( 'elementor/loaded' ) && isset( $data['tag_icon']['value'] ) && $data['tag_icon']['value'] ) {
						\Elementor\Icons_Manager::render_icon( $data['tag_icon'], [ 'aria-hidden' => 'true' ] );
					} else {
						echo "<i class='" . Fns::change_icon( 'fa fa-tags', 'tag' ) . "'></i>";
					}
				}
				echo wp_kses( $tags, self::allowedHtml() );
				?>
			</span>
			<?php
			echo wp_kses( $meta_separator, self::allowedHtml() );
		}
		$post_meta_html['tags'] = ob_get_clean();

		ob_start();
		// Comment Meta.
		if ( 'show' == $data['show_comment_count'] ) {
			?>
            <span class="comment-count">
				<?php
				if ( $data['show_meta_icon'] === 'yes' ) {
					if ( did_action( 'elementor/loaded' ) && isset( $data['comment_icon']['value'] ) && $data['comment_icon']['value'] ) {
						\Elementor\Icons_Manager::render_icon( $data['comment_icon'], [ 'aria-hidden' => 'true' ] );
					} else {
						echo "<i class='" . Fns::change_icon( 'fas fa-comments', 'chat' ) . "'></i>";
					}
				}
				echo wp_kses( $comments_text, self::allowedHtml() );
				?>
			</span>
			<?php
			echo wp_kses( $meta_separator, self::allowedHtml() );
		}

		$post_meta_html['comment_count'] = ob_get_clean();

		ob_start();
		// Post Count.
		if ( dwpAIC()->hasPro() && 'show' == $data['show_post_count'] && ! empty( $get_view_count ) ) {
			?>
            <span class="post-count">
				<?php
				if ( $data['show_meta_icon'] === 'yes' ) {
					if ( did_action( 'elementor/loaded' ) && isset( $data['post_count_icon']['value'] ) && $data['post_count_icon']['value'] ) {
						\Elementor\Icons_Manager::render_icon( $data['post_count_icon'], [ 'aria-hidden' => 'true' ] );
					} else {
						echo "<i class='" . Fns::change_icon( 'fa fa-eye', 'visible' ) . "'></i>";
					}
				}
				echo wp_kses( $get_view_count, self::allowedHtml() );
				?>
			</span>
			<?php
			echo wp_kses( $meta_separator, self::allowedHtml() );
		}

		$post_meta_html['post_count'] = ob_get_clean();

		if ( isset( $data['is_gutenberg'] ) && $data['is_gutenberg'] ) {
			$meta_ordering = array_keys( $post_meta_html );

			if ( isset( $data['meta_ordering'] ) && is_array( $data['meta_ordering'] ) ) {
				$meta_ordering      = wp_list_pluck( $data['meta_ordering'], 'value' );
				$extra_meta         = [];
				$post_meta_html_key = array_keys( $post_meta_html );

				$count_order = count( $meta_ordering );

				if ( $count_order != count( $post_meta_html_key ) ) {
					foreach ( $post_meta_html_key as $key ) {
						if ( ! in_array( $key, $meta_ordering ) ) {
							$extra_meta[] = $key;
						}
					}
				}

				if ( ! empty( $extra_meta ) ) {
					$meta_ordering = array_merge( $meta_ordering, $extra_meta );
				}
			}
			$_meta_html = "";

			foreach ( $meta_ordering as $val ) {
				$_meta_html .= $post_meta_html[ $val ];
//				echo $post_meta_html[ $val ];
			}

			if ( $echo ) {
				echo wp_kses_post( $_meta_html );
			} else {
				return $_meta_html;
			}

		} else {
			$meta_ordering = isset( $data['meta_ordering'] ) && is_array( $data['meta_ordering'] ) ? $data['meta_ordering'] : [];
			foreach ( $meta_ordering as $val ) {
				if ( isset( $post_meta_html[ $val['meta_name'] ] ) ) {
					echo wp_kses_post( $post_meta_html[ $val['meta_name'] ] );
				}

			}
		}
	}

	/**
	 * Custom wp_kses
	 *
	 * @param $string
	 *
	 * @return string
	 */
	public static function wp_kses( $string ) {
		$allowed_html = [
			'a'      => [
				'href'    => [],
				'title'   => [],
				'data-id' => [],
				'target'  => [],
				'class'   => [],
			],
			'strong' => [],
			'b'      => [],
			'br'     => [ [] ],
		];

		return wp_kses( $string, $allowed_html );
	}


	/**
	 * Get Elementor Post Title for Elementor
	 *
	 * @param $title_tag
	 * @param $title
	 * @param $link_start
	 * @param $link_end
	 * @param $data
	 */

	public static function get_el_post_title( $title_tag, $title, $link_start, $link_end, $data ) {

		echo '<div class="entry-title-wrapper">';

		if ( dwpAIC()->hasPro() && 'above_title' === $data['category_position'] || ! self::el_ignore_layout( $data ) ) {
			self::get_el_thumb_cat( $data, 'cat-above-title' );
		}

		printf( '<%s class="entry-title">', esc_attr( $title_tag ) );
		self::print_html( $link_start );
		self::print_html( $title );
		self::print_html( $link_end );
		printf( '</%s>', esc_attr( $title_tag ) );
		echo '</div>';
	}

	static function get_el_thumb_cat( $data, $class = 'cat-over-image' ) {
		if ( ! ( 'show' == $data['show_meta'] && 'show' == $data['show_category'] ) ) {
			return;
		}

		$pID               = get_the_ID();
		$_cat_id           = $data['post_type'] . '_taxonomy';
		$_post_taxonomy    = isset( $data[ $_cat_id ] ) ? $data[ $_cat_id ] : 'category';
		$categories        = self::rt_get_the_term_list( $pID, $_post_taxonomy, null, '<span class="rt-separator">,</span>' );
		$category_position = $data['category_position'];

		if ( in_array( $data['layout'], [
				'grid-layout4',
				'slider-layout3',
				'grid_hover-layout11'
			] ) && 'default' === $data['category_position'] ) {
			$category_position = 'top_left';
		}
		?>
        <div class="dowp-separate-category <?php echo esc_attr( $data['category_style'] . ' ' . $category_position . ' ' . $class ); ?>">
			<span class='categories-links'>
			<?php echo ( 'yes' === $data['show_cat_icon'] ) ? "<i class='" . Fns::change_icon( 'fas fa-folder-open', 'folder' ) . "'></i>" : null; ?>
			<?php echo wp_kses( $categories, self::allowedHtml() ); ?>
			</span>
        </div>
		<?php
	}


	/**
	 * Get first image from the content
	 *
	 * @param          $post_id
	 * @param string $type
	 *
	 * @return mixed|string
	 */
	public static function get_content_first_image( $post_id, $type = 'markup', $imgClass = '' ) {
		if ( $img = preg_match_all(
			'/<img.+src=[\'"]([^\'"]+)[\'"].*>/i',
			get_the_content( $post_id ),
			$matches
		)
		) {
			$imgSrc = $matches[1][0];
			$size   = '';

			$imgAbs = str_replace( trailingslashit( site_url() ), ABSPATH, $imgSrc );

			if ( file_exists( $imgAbs ) ) {
				$info = getimagesize( $imgAbs );
				$size = isset( $info[3] ) ? $info[3] : '';
			}

			$attachment_id = attachment_url_to_postid( $imgSrc );
			$alt_text      = null;

			if ( ! empty( $attachment_id ) ) {
				$alt_text = trim( wp_strip_all_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) );
			}

			$alt = $alt_text ? $alt_text : get_the_title( $post_id );

			if ( $type == 'markup' ) {
				if ( $imgClass !== 'swiper-lazy' ) {
					return "<img class='rt-img-responsive' src='{$imgSrc}' {$size} alt='{$alt}'>";
				} else {
					return "<img class='{$imgClass}' data-src='{$imgSrc}' alt='{$alt}'>";
				}
			} else {
				return $imgSrc;
			}
		}
	}

	/**
	 * Get post thumbnail html
	 *
	 * @param         $pID
	 * @param         $data
	 * @param         $link_start
	 * @param         $link_end
	 * @param false $offset_size
	 */
	public static function get_post_thumbnail( $pID, $data, $link_start, $link_end, $offset_size = false ) {
		$thumb_cat_condition = ( ! ( 'above_title' === $data['category_position'] || 'default' === $data['category_position'] ) );

		if ( 'grid-layout4' === $data['layout'] && 'default' === $data['category_position'] ) {
			$thumb_cat_condition = true;
		} else if ( in_array( $data['layout'], [
				'grid-layout4',
				'grid_hover-layout11',
				'slider-layout3'
			] ) && 'default' === $data['category_position'] ) {
			$thumb_cat_condition = true;
		}

		if ( dwpAIC()->hasPro() && $data['show_category'] == 'show' && $thumb_cat_condition && 'with_meta' !== $data['category_position'] ) {
			self::get_el_thumb_cat( $data );
		}

		$img_link     = get_the_post_thumbnail_url( $pID, 'full' );
		$img_size_key = 'image_size';


		if ( $offset_size ) {
			$img_size_key = 'image_offset_size';
		}

		$lazy_load  = ( $data['prefix'] == 'slider' && $data['lazy_load'] == 'yes' ) ? true : false;
		$lazy_class = 'rt-img-responsive';

		if ( $lazy_load ) {
			$lazy_class = 'swiper-lazy';
		}

		echo 'yes' === $data['is_thumb_linked'] ? self::print_html( $link_start ) : null;

		if ( has_post_thumbnail() && 'feature_image' === $data['media_source'] ) {
			$fImgSize = $data['image_size'];

			if ( $offset_size ) {
				echo get_the_post_thumbnail( $pID, $data['image_offset'] );
			} else {
				if ( $data['image_size'] !== 'custom' ) {
					$attachment_id = get_post_thumbnail_id( $pID );
					$thumb_info    = wp_get_attachment_image_src( $attachment_id, $fImgSize );
					$thumb_alt     = trim( wp_strip_all_tags( get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) );
					if ( $lazy_load ) {
						?>
                        <img data-src="<?php echo esc_url( isset( $thumb_info[0] ) ? $thumb_info[0] : '' ); ?>"
                             src="#none"
                             class="<?php echo esc_attr( $lazy_class ); ?>"
                             width="<?php echo esc_attr( isset( $thumb_info[1] ) ? $thumb_info[1] : '' ); ?>"
                             height="<?php echo esc_attr( isset( $thumb_info[2] ) ? $thumb_info[2] : '' ); ?>"
                             alt="<?php echo esc_attr( $thumb_alt ? $thumb_alt : the_title() ); ?>">
						<?php
					} else {
						?>
                        <img src="<?php echo esc_url( isset( $thumb_info[0] ) ? $thumb_info[0] : '' ); ?>"
                             class="<?php echo esc_attr( $lazy_class ); ?>"
                             width="<?php echo esc_attr( isset( $thumb_info[1] ) ? $thumb_info[1] : '' ); ?>"
                             height="<?php echo esc_attr( isset( $thumb_info[2] ) ? $thumb_info[2] : '' ); ?>"
                             alt="<?php echo esc_attr( $thumb_alt ? $thumb_alt : the_title() ); ?>">
						<?php
					}
					?>

					<?php
				} else {
					$fImgSize      = 'rt_custom';
					$mediaSource   = 'feature_image';
					$defaultImgId  = null;
					$customImgSize = [];


					if ( $data['is_gutenberg'] && isset( $data['c_image_width'] ) && isset( $data['c_image_height'] ) ) {
						$data['image_custom_dimension']['width']  = intval( $data['c_image_width'] );
						$data['image_custom_dimension']['height'] = intval( $data['c_image_height'] );
					}


					if ( isset( $data['image_custom_dimension'] ) ) {
						$post_thumb_id           = get_post_thumbnail_id( $pID );
						$default_image_dimension = wp_get_attachment_image_src( $post_thumb_id, 'full' );

						if ( $default_image_dimension[1] <= $data['image_custom_dimension']['width'] || $default_image_dimension[2] <= $data['image_custom_dimension']['height'] ) {
							$customImgSize = [];
						} else {
							$customImgSize[0] = $data['image_custom_dimension']['width'];
							$customImgSize[1] = $data['image_custom_dimension']['height'];
							$customImgSize[2] = $data['img_crop_style'];
						}
					}

					echo wp_kses_post( self::getFeatureImageSrc( $pID, $fImgSize, $mediaSource, $defaultImgId, $customImgSize, $lazy_class ) );
				}
			}
		} else if ( 'first_image' === $data['media_source'] && self::get_content_first_image( $pID ) ) {
			echo wp_kses_post( self::get_content_first_image( $pID, 'markup', $lazy_class ) );
			$img_link = self::get_content_first_image( $pID, 'url' );
		} else if ( 'yes' === $data['is_default_img'] || 'grid_hover' == $data['prefix'] ) {
			//echo \Elementor\Group_Control_Image_Size::get_attachment_image_html( $data, $img_size_key, 'default_image' );
			if ( isset( $data['default_image']['id'] ) ) {
				echo wp_get_attachment_image( $data['default_image']['id'], $data[ $img_size_key ], '', [ 'class' => 'rt-img-responsive' ] );
			}
			if ( ! empty( $data['default_image'] ) && isset( $data['default_image']['url'] ) ) {
				$img_link = $data['default_image']['url'];
			}
		}

		?>
		<?php if ( $lazy_load ) : ?>
            <div class="swiper-lazy-preloader swiper-lazy-preloader-white"></div>
		<?php endif; ?>

		<?php echo 'yes' === $data['is_thumb_linked'] ? wp_kses( $link_end, self::allowedHtml() ) : null; ?>

		<?php
		if ( 'show' === $data['is_thumb_lightbox'] || ( in_array( $data['layout'], [
					'grid-layout7',
					'slider-layout4'
				] ) && in_array( $data['is_thumb_lightbox'], [ 'default', 'show' ] ) ) ) :
			?>

            <a class="dowp-zoom mfp-fade"
               data-elementor-open-lightbox="yes"
               data-elementor-lightbox-slideshow="<?php echo esc_attr( $data['layout'] ); ?>"
               title="<?php echo esc_attr( get_the_title() ); ?>"
               href="<?php echo esc_url( $img_link ) ?>">

				<?php
				if ( did_action( 'elementor/loaded' ) && isset( $data['light_box_icon']['value'] ) && $data['light_box_icon']['value'] ) {
					\Elementor\Icons_Manager::render_icon( $data['light_box_icon'], [ 'aria-hidden' => 'true' ] );
				} else {
					echo "<i class='fa fa-plus'></i>";
				}
				?>
            </a>

		<?php endif; ?>
        <div class="overlay grid-hover-content"></div>
		<?php
	}


	/**
	 * Get ACF data for elementor
	 *
	 * @param $data
	 * @param $pID
	 *
	 * @return bool
	 */
	public static function tpg_get_acf_data_elementor( $data, $pID, $return_type = true ) {
		if ( ! ( dwpAIC()->hasPro() && self::is_acf() ) ) {
			return;
		}

		if ( isset( $data['show_acf'] ) && 'show' == $data['show_acf'] ) {
			$cf_group = $data['cf_group'];


			$format = [
				'hide_empty'       => ( isset( $data['cf_hide_empty_value'] ) && $data['cf_hide_empty_value'] ) ? 'yes' : '',
				'show_value'       => ( isset( $data['cf_show_only_value'] ) && $data['cf_show_only_value'] ) ? '' : 'yes',
				'hide_group_title' => ( isset( $data['cf_hide_group_title'] ) && $data['cf_hide_group_title'] ) ? '' : 'yes',
			];

			if ( ! empty( $cf_group ) ) {

				$acf_html = "<div class='acf-custom-field-wrap'>";

				$acf_html .= \RT\ThePostGridPro\Helpers\Functions::get_cf_formatted_fields( $cf_group, $format, $pID );
				$acf_html .= '</div>';

				if ( $return_type ) {
					self::print_html( $acf_html, true );
				} else {
					return $acf_html;
				}
			}
		}
	}

	/**
	 * Get Read More Button
	 *
	 * @param $data
	 * @param $readmore_link_start
	 * @param $readmore_link_end
	 *
	 * @return void
	 */
	public static function get_read_more_button( $data, $readmore_link_start, $readmore_link_end, $type = 'elementor' ) {
		?>
        <div class="post-footer">
            <div class="post-footer">
                <div class="read-more">
					<?php
					echo self::wp_kses( $readmore_link_start );
					if ( 'yes' == $data['show_btn_icon'] && 'left' == $data['readmore_icon_position'] ) {
						if ( $type === 'elementor' ) {
							if ( did_action( 'elementor/loaded' ) ) {
								\Elementor\Icons_Manager::render_icon( $data['readmore_btn_icon'], [
									'aria-hidden' => 'true',
									'class'       => 'left-icon'
								] );
							}
						} else {
							//TODO: FlatIcon should chagne
							echo "<i class='left-icon " . Fns::change_icon( 'fas fa-angle-right', 'left-arrow', 'left-icon' ) . "'></i>";
						}
					}
					echo esc_html( $data['read_more_label'] );
					if ( 'yes' == $data['show_btn_icon'] && 'right' == $data['readmore_icon_position'] ) {
						if ( $type === 'elementor' ) {
							if ( did_action( 'elementor/loaded' ) ) {
								\Elementor\Icons_Manager::render_icon( $data['readmore_btn_icon'], [
									'aria-hidden' => 'true',
									'class'       => 'right-icon'
								] );
							}
						} else {
							//TODO: FlatIcon should chagne
							echo "<i class='left-icon " . Fns::change_icon( 'fas fa-angle-right', 'right-arrow', 'right-icon' ) . "'></i>";
						}
					}
					echo self::wp_kses( $readmore_link_end );
					?>
                </div>
            </div>
        </div>
		<?php
	}


	/**
	 * Check is filter enable or not
	 *
	 * @param $data
	 *
	 * @return bool
	 */
	public static function is_filter_enable( $data ) {
		if ( dwpAIC()->hasPro()
		     && ( $data['show_taxonomy_filter'] == 'show'
		          || $data['show_author_filter'] == 'show'
		          || $data['show_order_by'] == 'show'
		          || $data['show_sort_order'] == 'show'
		          || $data['show_search'] == 'show'
		          || ( $data['show_pagination'] == 'show' && $data['pagination_type'] != 'pagination' ) )
		) {
			return true;
		}

		return false;
	}


	//Get Custom post category:
	public static function tpg_get_categories_by_id( $cat = 'category' ) {
		$terms = get_terms( [
			'taxonomy'   => $cat,
			'hide_empty' => true,
		] );

		$options = [];
		if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ $term->term_id ] = $term->name;
			}
		}

		return $options;
	}



	/**
	 * Gutenberg Functionality
	 * ::::::::::::::::::::::::::::::::::::::::::::::::::::::::::
	 */

	/**
	 * Get Post Types.
	 *
	 * @since 1.0.9
	 */
	public static function get_post_types_guten() {
		$post_types = get_post_types(
			[
				'public'       => true,
				'show_in_rest' => true,
			],
			'objects'
		);

		$options = [];

		foreach ( $post_types as $post_type ) {
			if ( 'product' === $post_type->name ) {
				continue;
			}

			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			if ( 'page' === $post_type->name ) {
				continue;
			}

			$options[] = [
				'value' => $post_type->name,
				'label' => $post_type->label,
			];
		}

		return $options;
	}

	/**
	 * Get all taxonomies.
	 *
	 * @since 1.0.9
	 */

	public static function get_all_taxonomy_guten() {
		$post_types     = Fns::get_post_types();
		$taxonomies     = get_taxonomies( [], 'objects' );
		$all_taxonomies = [];
		foreach ( $taxonomies as $taxonomy => $object ) {
			if ( ! isset( $object->object_type[0] ) || ! in_array( $object->object_type[0], array_keys( $post_types ) )
			     || in_array( $taxonomy, Fns::get_excluded_taxonomy() )
			) {
				continue;
			}
			$all_taxonomies[ $taxonomy ] = Fns::tpg_get_categories_by_id( $taxonomy );
		}

		return $all_taxonomies;

	}

	/**
	 * Get all image sizes.
	 *
	 * @since 1.0.9
	 */
	public static function get_all_image_sizes_guten() {
		global $_wp_additional_image_sizes;

		$sizes       = get_intermediate_image_sizes();
		$image_sizes = [];

		$image_sizes[] = [
			'value' => 'full',
			'label' => esc_html__( 'Full', 'ai-content-generate' ),
		];

		foreach ( $sizes as $size ) {
			if ( in_array( $size, [ 'thumbnail', 'medium', 'medium_large', 'large' ], true ) ) {
				$image_sizes[] = [
					'value' => $size,
					'label' => ucwords( trim( str_replace( [ '-', '_' ], [ ' ', ' ' ], $size ) ) ),
				];
			} else {
				$image_sizes[] = [
					'value' => $size,
					'label' => sprintf(
						'%1$s (%2$sx%3$s)',
						ucwords( trim( str_replace( [ '-', '_' ], [ ' ', ' ' ], $size ) ) ),
						$_wp_additional_image_sizes[ $size ]['width'],
						$_wp_additional_image_sizes[ $size ]['height']
					),
				];
			}
		}

		if ( dwpAIC()->hasPro() ) {
			$image_sizes[] = [
				'value' => 'custom',
				'label' => esc_html__( 'Custom', 'ai-content-generate' ),
			];
		}

		return apply_filters( 'tpg_image_size_guten', $image_sizes );
	}


	/**
	 * Prints HTML.
	 *
	 * @param string $html HTML.
	 * @param bool $allHtml All HTML.
	 *
	 * @return mixed
	 */
	public static function print_html( $html, $allHtml = false ) {
		if ( $allHtml ) {
			echo stripslashes_deep( $html );
		} else {
			echo wp_kses_post( stripslashes_deep( $html ) );
		}
	}

	/**
	 * Allowed HTML for wp_kses.
	 *
	 * @param string $level Tag level.
	 *
	 * @return mixed
	 */
	public static function allowedHtml( $level = 'basic' ) {
		$allowed_html = [];

		switch ( $level ) {
			case 'basic':
				$allowed_html = [
					'b'      => [
						'class' => [],
						'id'    => [],
					],
					'i'      => [
						'class' => [],
						'id'    => [],
					],
					'u'      => [
						'class' => [],
						'id'    => [],
					],
					'br'     => [
						'class' => [],
						'id'    => [],
					],
					'em'     => [
						'class' => [],
						'id'    => [],
					],
					'span'   => [
						'class' => [],
						'id'    => [],
					],
					'strong' => [
						'class' => [],
						'id'    => [],
					],
					'hr'     => [
						'class' => [],
						'id'    => [],
					],
					'a'      => [
						'href'   => [],
						'title'  => [],
						'class'  => [],
						'id'     => [],
						'target' => [],
						'style'  => [],
					],
					'div'    => [
						'class' => [],
						'id'    => [],
					],
				];
				break;

			case 'advanced':
				$allowed_html = [
					'b'      => [
						'class' => [],
						'id'    => [],
					],
					'i'      => [
						'class' => [],
						'id'    => [],
					],
					'u'      => [
						'class' => [],
						'id'    => [],
					],
					'br'     => [
						'class' => [],
						'id'    => [],
					],
					'em'     => [
						'class' => [],
						'id'    => [],
					],
					'span'   => [
						'class' => [],
						'id'    => [],
					],
					'strong' => [
						'class' => [],
						'id'    => [],
					],
					'hr'     => [
						'class' => [],
						'id'    => [],
					],
					'a'      => [
						'href'    => [],
						'title'   => [],
						'class'   => [],
						'id'      => [],
						'data-id' => [],
						'target'  => [],
					],
					'input'  => [
						'type'  => [],
						'name'  => [],
						'class' => [],
						'value' => [],
					],
				];
				break;

			case 'image':
				$allowed_html = [
					'img' => [
						'src'      => [],
						'data-src' => [],
						'alt'      => [],
						'height'   => [],
						'width'    => [],
						'class'    => [],
						'id'       => [],
						'style'    => [],
						'srcset'   => [],
						'loading'  => [],
						'sizes'    => [],
					],
					'div' => [
						'class' => [],
					],
				];
				break;

			case 'anchor':
				$allowed_html = [
					'a' => [
						'href'  => [],
						'title' => [],
						'class' => [],
						'id'    => [],
						'style' => [],
					],
				];
				break;

			default:
				// code...
				break;
		}

		return $allowed_html;
	}

	/**
	 * Definition for wp_kses.
	 *
	 * @param string $string String to check.
	 * @param string $level Tag level.
	 *
	 * @return mixed
	 */
	public static function htmlKses( $string, $level ) {
		if ( empty( $string ) ) {
			return;
		}

		return wp_kses( $string, self::allowedHtml( $level ) );
	}


	/**
	 * Insert Array Item in specific position
	 *
	 * @param $array
	 * @param $position
	 * @param $insert_array
	 *
	 * @return void
	 */
	public static function array_insert( &$array, $position, $insert_array ) {
		$first_array = array_splice( $array, 0, $position + 1 );
		$array       = array_merge( $first_array, $insert_array, $array );
	}

	/**
	 * tpg_option
	 *
	 * @param $option_name
	 * @param $default_value
	 *
	 * @return string
	 */
	public static function tpg_option( $option_name, $default_value = '' ) {
		$settings = get_option( dwpAIC()->options['settings'] );
		if ( ! empty( $settings[ $option_name ] ) ) {
			return $settings[ $option_name ];
		} else if ( $default_value ) {
			return $default_value;
		}

		return '';
	}

	/**
	 * change_icon
	 *
	 * @param $fontawesome
	 * @param $flaticon
	 * @param $default_class
	 *
	 * @return mixed|string
	 */
	public static function change_icon( $fontawesome, $flaticon, $default_class = '' ) {
		if ( self::tpg_option( 'tpg_icon_font' ) === 'fontawesome' ) {
			$fontawesome = ( $fontawesome === 'fab fa-twitter' ? 'fab fa-x-twitter' : $fontawesome );

			return $fontawesome . ' ' . $default_class;
		} else {
			$flaticon = ( $flaticon == 'twitter' ? 'twitter-x' : $flaticon );

			return 'flaticon-' . $flaticon . ' ' . $default_class;
		}
	}

	/**
	 * Get Instant Query Argument
	 *
	 * @param $instant_query
	 * @param $args
	 *
	 * @return mixed
	 */
	public static function get_instant_query( $instant_query, $args ) {
		if ( 'default' === $instant_query ) {
			return $args;
		}

		switch ( $instant_query ) {
			case 'random_post_7_days':
			case 'random_post_30_days':
				$args['orderby']    = 'rand';
				$args['order']      = 'ASC';
				$args['date_query'] = [ [ 'after' => $instant_query === 'random_post_7_days' ? '1 week ago' : '1 month ago' ] ];
				break;
			case 'most_comment_1_day':
			case 'most_comment_7_days':
			case 'most_comment_30_days':
				$args['orderby']    = 'comment_count';
				$args['order']      = 'DESC';
				$args['date_query'] = [ [ 'after' => $instant_query === 'most_comment_1_day' ? '1 day ago' : ( $instant_query === 'most_comment_7_days' ? '1 week ago' : '1 month ago' ) ] ];
				break;
			case 'popular_post_1_day_view':
			case 'popular_post_7_days_view':
			case 'popular_post_30_days_view':
			case 'popular_post_all_times_view':
				$args['meta_key'] = Fns::get_post_view_count_meta_key();
				$args['orderby']  = 'meta_value_num';
				$args['order']    = 'DESC';
				if ( $instant_query !== 'popular_post_all_times_view' ) {
					$args['date_query'] = [ [ 'after' => ( $instant_query === 'popular_post_1_day_view' ? '1 day ago' : ( $instant_query === 'popular_post_7_days_view' ? '1 week ago' : '1 month ago' ) ) ] ];
				}
				break;
			case 'related_category':
				global $post;
				$p_id = isset( $post->ID ) && $post->ID ? $post->ID : ( isset( $prams['current_post'] ) && $prams['current_post'] ? $prams['current_post'] : ( isset( $_POST['postId'] ) ? sanitize_text_field( $_POST['postId'] ) : '' ) ); //phpcs:disable ordPress.Security.NonceVerification.Missing
				if ( $p_id ) {
					$args['tax_query']    = [
						[
							'taxonomy' => 'category',
							'terms'    => self::get_terms_id( $p_id, 'category' ),
							'field'    => 'term_id',
						]
					];
					$args['post__not_in'] = [ $p_id ];
				}
				break;
			case 'related_tag':
				global $post;
				$p_id = isset( $post->ID ) && $post->ID ? $post->ID : ( isset( $prams['current_post'] ) && $prams['current_post'] ? $prams['current_post'] : ( isset( $_POST['postId'] ) ? sanitize_text_field( $_POST['postId'] ) : '' ) ); //phpcs:disable ordPress.Security.NonceVerification.Missing
				if ( $p_id ) {
					$args['tax_query']    = [
						[
							'taxonomy' => 'post_tag',
							'terms'    => self::get_terms_id( $p_id, 'post_tag' ),
							'field'    => 'term_id',
						]
					];
					$args['post__not_in'] = [ $p_id ];
				}
				break;
			case 'related_cat_tag':
				global $post;
				$p_id = isset( $post->ID ) && $post->ID ? $post->ID : ( isset( $prams['current_post'] ) && $prams['current_post'] ? $prams['current_post'] : ( isset( $_POST['postId'] ) ? sanitize_text_field( $_POST['postId'] ) : '' ) ); //phpcs:disable ordPress.Security.NonceVerification.Missing
				if ( $p_id ) {
					$args['tax_query']    = [
						[
							'taxonomy' => 'post_tag',
							'terms'    => self::get_terms_id( $p_id, 'post_tag' ),
							'field'    => 'term_id',
						],
						[
							'taxonomy' => 'category',
							'terms'    => self::get_terms_id( $p_id, 'category' ),
							'field'    => 'term_id',
						]
					];
					$args['post__not_in'] = [ $p_id ];
				}
				break;
			default:
				break;
		}

		return $args;
	}

	public static function get_terms_id( $id, $type ) {
		$data = [];
		$arr  = get_the_terms( $id, $type );
		if ( is_array( $arr ) ) {
			foreach ( $arr as $key => $val ) {
				$data[] = $val->term_id;
			}
		}

		return $data;
	}

	/**
	 *
	 * Get last post id
	 *
	 * @param string $post_type
	 * @param false $all_content
	 *
	 * @return int
	 */
	public static function get_last_post_id( $post_type = 'post' ): int {
		if ( is_singular( $post_type ) ) {
			return get_the_ID();
		}

		global $wpdb;
		$cache_key = 'tpg_last_post_id';
		$_post_id  = get_transient( $cache_key );

		if ( false === $_post_id || 'publish' !== get_post_status( $_post_id ) ) {
			delete_transient( $cache_key );
			$_post_id = $wpdb->get_var(
				$wpdb->prepare( "SELECT MAX(ID) FROM {$wpdb->prefix}posts WHERE post_type = %s AND post_status = %s", $post_type, 'publish' )
			);
			set_transient( $cache_key, $_post_id, 12 * HOUR_IN_SECONDS );
		}

		return absint( $_post_id );
	}

	/**
	 * Gutenberg
	 * @return mixed|null
	 */
	public static function get_builder_type_list() {
		return apply_filters( 'tpg_builder_type_list', [
			'single'           => __( "Single", "ai-content-generate" ),
			'archive'          => __( "Post Archive", "ai-content-generate" ),
			'author-archive'   => __( "Author Archive", "ai-content-generate" ),
			'search-archive'   => __( "Search Archive", "ai-content-generate" ),
			'date-archive'     => __( "Date Archive", "ai-content-generate" ),
			'category-archive' => __( "Category Archive", "ai-content-generate" ),
			'tags-archive'     => __( "Tags Archive", "ai-content-generate" ),
		] );
	}
}