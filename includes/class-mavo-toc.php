<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mavo_TOC {

	const OPTION_KEY = 'mavo_toc_options';

	public function __construct() {
		add_shortcode( 'mavo_toc', array( $this, 'shortcode' ) );
		add_filter( 'the_content', array( $this, 'render' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Hardcoded fallback values, used when an option has never been saved.
	 */
	public static function get_defaults() {
		return array(
			'title'         => __( 'Table of Contents', 'mavo-toc' ),
			'min_level'     => 2,
			'max_level'     => 4,
			'collapsible'   => true,
			'collapsed'     => false,
			'sticky'        => false,
			'numbered'      => false,
			'smooth_scroll' => true,
			'limit'         => 0,
			'exclude_class' => 'mavo-toc-skip',
		);
	}

	/**
	 * Saved settings merged on top of the hardcoded defaults.
	 */
	public static function get_options() {
		$saved = get_option( self::OPTION_KEY, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::get_defaults() );
	}

	public function register_assets() {
		wp_register_style( 'mavo-toc', MAVO_TOC_URL . 'assets/css/mavo-toc.css', array(), MAVO_TOC_VERSION );
		wp_register_script( 'mavo-toc', MAVO_TOC_URL . 'assets/js/mavo-toc.js', array(), MAVO_TOC_VERSION, true );
	}

	/**
	 * The shortcode never outputs the table of contents directly: the post content
	 * isn't fully assembled yet (other shortcodes/blocks may still render further
	 * headings), so it drops a placeholder marker that render() resolves once the
	 * whole content string for the post is final.
	 */
	public function shortcode( $atts ) {
		if ( is_feed() ) {
			return '';
		}

		$options = self::get_options();

		$atts = shortcode_atts(
			array(
				'title'         => $options['title'],
				'min_level'     => $options['min_level'],
				'max_level'     => $options['max_level'],
				'collapsible'   => $options['collapsible'] ? 'true' : 'false',
				'collapsed'     => $options['collapsed'] ? 'true' : 'false',
				'sticky'        => $options['sticky'] ? 'true' : 'false',
				'numbered'      => $options['numbered'] ? 'true' : 'false',
				'smooth_scroll' => $options['smooth_scroll'] ? 'true' : 'false',
				'limit'         => $options['limit'],
				'class'         => '',
			),
			$atts,
			'mavo_toc'
		);

		$atts['min_level']     = max( 1, min( 6, (int) $atts['min_level'] ) );
		$atts['max_level']     = max( $atts['min_level'], min( 6, (int) $atts['max_level'] ) );
		$atts['limit']         = max( 0, (int) $atts['limit'] );
		$atts['collapsible']   = filter_var( $atts['collapsible'], FILTER_VALIDATE_BOOLEAN );
		$atts['collapsed']     = filter_var( $atts['collapsed'], FILTER_VALIDATE_BOOLEAN );
		$atts['sticky']        = filter_var( $atts['sticky'], FILTER_VALIDATE_BOOLEAN );
		$atts['numbered']      = filter_var( $atts['numbered'], FILTER_VALIDATE_BOOLEAN );
		$atts['smooth_scroll'] = filter_var( $atts['smooth_scroll'], FILTER_VALIDATE_BOOLEAN );

		wp_enqueue_style( 'mavo-toc' );
		wp_enqueue_script( 'mavo-toc' );

		return '<!--MAVO_TOC ' . base64_encode( wp_json_encode( $atts ) ) . '-->';
	}

	/**
	 * Runs late on `the_content` (priority 100), after blocks/shortcodes/wpautop
	 * have produced the final HTML. Scans the content once for headings (adding
	 * anchor ids where missing) and then resolves every placeholder marker left
	 * behind by shortcode().
	 */
	public function render( $content ) {
		if ( false === strpos( $content, '<!--MAVO_TOC ' ) ) {
			return $content;
		}

		$headings = $this->extract_headings( $content );

		if ( ! preg_match_all( '/<!--MAVO_TOC ([a-zA-Z0-9+\/=]+)-->/', $content, $matches ) ) {
			return $content;
		}

		foreach ( array_unique( $matches[1] ) as $encoded ) {
			$atts = json_decode( base64_decode( $encoded ), true );
			$html = is_array( $atts ) ? $this->build_html( $headings, $atts ) : '';
			$content = str_replace( '<!--MAVO_TOC ' . $encoded . '-->', $html, $content );
		}

		return $content;
	}

	/**
	 * Finds every h1-h6 in $content, assigns a unique slug id to any heading that
	 * doesn't already have one (Gutenberg's "HTML anchor" field is left untouched),
	 * and returns a flat, document-order list of the headings that aren't marked
	 * with the exclude class.
	 */
	private function extract_headings( &$content ) {
		$headings      = array();
		$used_ids      = array();
		$exclude_class = self::get_options()['exclude_class'];

		$content = preg_replace_callback(
			'/<h([1-6])([^>]*)>(.*?)<\/h\1>/is',
			function ( $m ) use ( &$headings, &$used_ids, $exclude_class ) {
				$level = (int) $m[1];
				$attrs = $m[2];
				$inner = $m[3];
				$text  = trim( html_entity_decode( wp_strip_all_tags( $inner ), ENT_QUOTES ) );

				if ( '' === $text ) {
					return $m[0];
				}

				$excluded = false;
				if ( $exclude_class && preg_match( '/class=["\'][^"\']*' . preg_quote( $exclude_class, '/' ) . '[^"\']*["\']/i', $attrs ) ) {
					$excluded = true;
				}

				if ( preg_match( '/\bid=["\']([^"\']+)["\']/i', $attrs, $id_match ) ) {
					$id = $id_match[1];
				} else {
					$base = sanitize_title( $text );
					$id   = $base;
					$i    = 2;
					while ( isset( $used_ids[ $id ] ) ) {
						$id = $base . '-' . $i++;
					}
				}
				$used_ids[ $id ] = true;

				if ( ! $excluded ) {
					$headings[] = array(
						'level' => $level,
						'id'    => $id,
						'text'  => $text,
					);
				}

				if ( preg_match( '/\bid=["\']/i', $attrs ) ) {
					return $m[0];
				}

				return "<h{$level}{$attrs} id=\"" . esc_attr( $id ) . "\">{$inner}</h{$level}>";
			},
			$content
		);

		return $headings;
	}

	private function build_html( array $headings, array $atts ) {
		$items = array_values(
			array_filter(
				$headings,
				function ( $h ) use ( $atts ) {
					return $h['level'] >= $atts['min_level'] && $h['level'] <= $atts['max_level'];
				}
			)
		);

		if ( empty( $items ) ) {
			return '';
		}

		$index = 0;
		$tree  = $this->build_branch( $items, $index, 0 );

		$tag     = $atts['numbered'] ? 'ol' : 'ul';
		$counter = 0;
		$list    = $this->render_branch( $tree, $tag, (int) $atts['limit'], $counter );

		$classes = array( 'mavo-toc' );
		if ( $atts['sticky'] ) {
			$classes[] = 'mavo-toc--sticky';
		}
		if ( $atts['collapsible'] ) {
			$classes[] = 'mavo-toc--collapsible';
		}
		if ( $atts['collapsible'] && $atts['collapsed'] ) {
			$classes[] = 'mavo-toc--collapsed';
		}
		if ( ! empty( $atts['class'] ) ) {
			$classes[] = sanitize_html_class( $atts['class'] );
		}

		$smooth_attr = $atts['smooth_scroll'] ? ' data-smooth-scroll="1"' : '';

		$html = '<nav class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $smooth_attr . '>';

		if ( ! empty( $atts['title'] ) ) {
			if ( $atts['collapsible'] ) {
				$html .= '<button type="button" class="mavo-toc__title" aria-expanded="' . ( $atts['collapsed'] ? 'false' : 'true' ) . '">' . esc_html( $atts['title'] ) . '</button>';
			} else {
				$html .= '<p class="mavo-toc__title">' . esc_html( $atts['title'] ) . '</p>';
			}
		}

		$html .= '<div class="mavo-toc__body">';
		$html .= $list;

		if ( $atts['limit'] > 0 && $counter > $atts['limit'] ) {
			$html .= '<button type="button" class="mavo-toc__toggle" data-label-more="' . esc_attr__( 'Show more', 'mavo-toc' ) . '" data-label-less="' . esc_attr__( 'Show less', 'mavo-toc' ) . '">' . esc_html__( 'Show more', 'mavo-toc' ) . '</button>';
		}

		$html .= '</div></nav>';

		return $html;
	}

	/**
	 * Groups a flat, document-order list of headings into a tree based on level,
	 * tolerating skipped or out-of-order levels (e.g. h2 -> h4 -> h3): anything
	 * deeper than $parent_level becomes a child, anything else is left for the
	 * caller.
	 */
	private function build_branch( array $items, &$index, $parent_level ) {
		$branch = array();
		$count  = count( $items );

		while ( $index < $count && $items[ $index ]['level'] > $parent_level ) {
			$level = $items[ $index ]['level'];
			$node  = array(
				'heading'  => $items[ $index ],
				'children' => array(),
			);
			++$index;
			$node['children'] = $this->build_branch( $items, $index, $level );
			$branch[]          = $node;
		}

		return $branch;
	}

	private function render_branch( array $branch, $tag, $limit, &$counter ) {
		if ( empty( $branch ) ) {
			return '';
		}

		$html = "<{$tag} class=\"mavo-toc__list\">";

		foreach ( $branch as $node ) {
			++$counter;
			$hidden = ( $limit > 0 && $counter > $limit ) ? ' mavo-toc__item--hidden' : '';
			$html  .= '<li class="mavo-toc__item' . $hidden . '">';
			$html  .= '<a href="#' . esc_attr( $node['heading']['id'] ) . '">' . esc_html( $node['heading']['text'] ) . '</a>';
			$html  .= $this->render_branch( $node['children'], $tag, $limit, $counter );
			$html  .= '</li>';
		}

		$html .= "</{$tag}>";

		return $html;
	}
}
