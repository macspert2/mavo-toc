<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mavo_TOC {

	const OPTION_KEY = 'mavo_toc_options';

	const ASSETS_FINGERPRINT_OPTION = 'mavo_toc_assets_fingerprint';

	/** @var array Temporary diagnostic for the Polylang title-language issue. */
	private $textdomain_debug = array();

	public function __construct() {
		add_shortcode( 'mavo_toc', array( $this, 'shortcode' ) );
		add_filter( 'the_content', array( $this, 'render' ), 100 );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'init', array( $this, 'maybe_purge_autoptimize' ) );
		// `init` covers wp-admin (the settings page) and is a safe baseline, but
		// pll_current_language() isn't reliable for *which post* is being viewed
		// until the main query has resolved it, so this also re-runs on `wp`
		// (after that), which is what actually decides the front-end title's
		// language. The later call simply wins if it differs from the first.
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp', array( $this, 'load_textdomain' ) );
		add_action( 'wp_footer', array( $this, 'print_textdomain_debug' ), 9999 );
	}

	/**
	 * Uses Polylang's own slug (pll_current_language(), e.g. "fr"/"en"/"de")
	 * directly when available, since it's stable regardless of which exact
	 * WordPress locale (fr_FR vs fr_CA, de_DE vs de_DE_formal...) each language
	 * is mapped to in Polylang's settings. Falls back to the standard WordPress
	 * locale-based loading for sites not running Polylang.
	 */
	public function load_textdomain() {
		global $l10n;

		$debug = array(
			'hook'                  => current_filter(),
			'pll_active'            => function_exists( 'pll_current_language' ) ? '1' : '0',
			'pll_slug'              => function_exists( 'pll_current_language' ) ? (string) pll_current_language( 'slug' ) : 'n/a',
			'get_locale'            => get_locale(),
			'already_loaded_before' => isset( $l10n['mavo-toc'] ) ? '1' : '0',
			'mofile_exists'         => '0',
			'load_textdomain_ok'    => '0',
			'sample_translation'    => '',
		);

		// Defensive: load_textdomain() *merges* with anything already loaded for
		// this domain, and existing entries win over the newly loaded ones for
		// matching strings — so if anything loaded this domain earlier in the
		// request (e.g. WP's just-in-time loader, triggered by an early __()
		// call, using whatever locale was active before Polylang switched it),
		// our correct load could be silently overridden. Unloading first
		// guarantees a clean slate.
		unload_textdomain( 'mavo-toc' );

		$used_pll_path = false;

		if ( function_exists( 'pll_current_language' ) ) {
			$slug = pll_current_language( 'slug' );
			if ( $slug ) {
				$mofile                  = MAVO_TOC_PATH . 'languages/mavo-toc-' . $slug . '.mo';
				$debug['mofile_path']    = $mofile;
				$debug['mofile_exists']  = file_exists( $mofile ) ? '1' : '0';
				if ( file_exists( $mofile ) ) {
					$loaded                       = load_textdomain( 'mavo-toc', $mofile );
					$debug['load_textdomain_ok']  = $loaded ? '1' : '0';
					if ( $loaded ) {
						$used_pll_path = true;
					}
				}
			}
		}

		if ( ! $used_pll_path ) {
			load_plugin_textdomain( 'mavo-toc', false, dirname( plugin_basename( MAVO_TOC_FILE ) ) . '/languages' );
		}

		$debug['used_pll_path']      = $used_pll_path ? '1' : '0';
		$debug['sample_translation'] = __( 'Table of Contents', 'mavo-toc' );

		$this->textdomain_debug[ current_filter() ] = $debug;
	}

	/**
	 * Temporary diagnostic for the Polylang title-language issue — safe to
	 * remove once resolved. Prints nothing visible; only an HTML comment.
	 */
	public function print_textdomain_debug() {
		$this->textdomain_debug['at_footer'] = array(
			'pll_slug'           => function_exists( 'pll_current_language' ) ? (string) pll_current_language( 'slug' ) : 'n/a',
			'get_locale'         => get_locale(),
			'sample_translation' => __( 'Table of Contents', 'mavo-toc' ),
		);

		if ( empty( $this->textdomain_debug ) ) {
			return;
		}
		echo "\n<!-- mavo-toc-lang-debug " . esc_html( wp_json_encode( $this->textdomain_debug ) ) . " -->\n";
	}

	/**
	 * Hardcoded fallback values, used when an option has never been saved.
	 */
	public static function get_defaults() {
		return array(
			'title'               => __( 'Table of Contents', 'mavo-toc' ),
			'min_level'           => 2,
			'max_level'           => 4,
			'collapsible'         => true,
			'collapsed'           => false,
			'sticky'              => false,
			'numbered'            => false,
			'smooth_scroll'       => true,
			'limit'               => 0,
			'exclude_class'       => 'mavo-toc-skip',
			'sticky_bar_selector' => '.mavo-sticky',
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
		wp_register_style( 'mavo-toc', MAVO_TOC_URL . 'assets/css/mavo-toc.css', array(), self::asset_version( 'assets/css/mavo-toc.css' ) );
		wp_register_script( 'mavo-toc', MAVO_TOC_URL . 'assets/js/mavo-toc.js', array(), self::asset_version( 'assets/js/mavo-toc.js' ), true );
	}

	/**
	 * Versions an asset by its own filemtime instead of the plugin version, so the
	 * enqueued URL (and therefore Autoptimize's aggregation key for it) changes
	 * automatically whenever the file is edited, without anyone having to
	 * remember to bump a version constant by hand.
	 */
	private static function asset_version( $relative_path ) {
		$file = MAVO_TOC_PATH . $relative_path;
		return file_exists( $file ) ? (string) filemtime( $file ) : MAVO_TOC_VERSION;
	}

	/**
	 * Autoptimize aggregates and caches CSS/JS site-wide independently of our own
	 * asset URLs, so an edited mavo-toc.css/.js can still serve a stale aggregate
	 * even though register_assets() is already pointing at a fresh URL. This runs
	 * a cheap filemtime check on every request and purges Autoptimize's cache the
	 * moment either file actually changes on disk, so cache-busting is automatic
	 * rather than relying on remembering a manual "clear cache" step after every
	 * deploy.
	 */
	public function maybe_purge_autoptimize() {
		$fingerprint = self::asset_version( 'assets/css/mavo-toc.css' ) . '-' . self::asset_version( 'assets/js/mavo-toc.js' );
		$stored      = get_option( self::ASSETS_FINGERPRINT_OPTION );

		if ( $stored === $fingerprint ) {
			return;
		}

		update_option( self::ASSETS_FINGERPRINT_OPTION, $fingerprint );

		// false only on the very first run (option never set yet): nothing to purge.
		if ( false !== $stored && class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
			autoptimizeCache::clearall();
		}
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

		if ( ! isset( $this->textdomain_debug['at_shortcode_render'] ) ) {
			$this->textdomain_debug['at_shortcode_render'] = array(
				'pll_slug'    => function_exists( 'pll_current_language' ) ? (string) pll_current_language( 'slug' ) : 'n/a',
				'get_locale'  => get_locale(),
				'title_value' => $options['title'],
			);
		}

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

		$tag       = $atts['numbered'] ? 'ol' : 'ul';
		$counter   = 0;
		$max_depth = 0;
		$list      = $this->render_branch( $tree, $tag, (int) $atts['limit'], $counter, 0, $max_depth );

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

		$extra_attrs = $atts['smooth_scroll'] ? ' data-smooth-scroll="1"' : '';

		// Needed even when this particular TOC isn't sticky: the site's fixed menu
		// bar still obstructs the top of the viewport, so smooth-scroll jumps need
		// its height regardless of this instance's own sticky setting.
		$sticky_bar_selector = self::get_options()['sticky_bar_selector'];
		if ( $sticky_bar_selector ) {
			$extra_attrs .= ' data-sticky-bar="' . esc_attr( $sticky_bar_selector ) . '"';
		}

		$html = '<nav class="' . esc_attr( implode( ' ', $classes ) ) . '"' . $extra_attrs . '>';

		if ( ! empty( $atts['title'] ) ) {
			if ( $atts['collapsible'] ) {
				$html .= '<button type="button" class="mavo-toc__title" aria-expanded="' . ( $atts['collapsed'] ? 'false' : 'true' ) . '">' . esc_html( $atts['title'] ) . '</button>';
			} else {
				$html .= '<p class="mavo-toc__title">' . esc_html( $atts['title'] ) . '</p>';
			}
		}

		$html .= '<div class="mavo-toc__body">';
		$html .= $list;

		if ( $max_depth > 0 ) {
			$html .= '<button type="button" class="mavo-toc__btn mavo-toc__btn--levels" aria-expanded="false" data-label-expand="' . esc_attr__( 'Show subheadings', 'mavo-toc' ) . '" data-label-collapse="' . esc_attr__( 'Hide subheadings', 'mavo-toc' ) . '">' . esc_html__( 'Show subheadings', 'mavo-toc' ) . '</button>';
		}

		if ( $atts['limit'] > 0 && $counter > $atts['limit'] ) {
			$html .= '<button type="button" class="mavo-toc__btn mavo-toc__btn--more" data-label-more="' . esc_attr__( 'Show more', 'mavo-toc' ) . '" data-label-less="' . esc_attr__( 'Show less', 'mavo-toc' ) . '">' . esc_html__( 'Show more', 'mavo-toc' ) . '</button>';
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

	/**
	 * $depth is the nesting depth relative to the shallowest headings shown (0 for
	 * the top-level list), not the literal h1-h6 number, so progressive reveal
	 * works the same way regardless of which heading levels are actually in range.
	 * Every list rendered at depth > 0 is tagged with that depth so the front-end
	 * script can reveal one more level at a time; $max_depth records how deep the
	 * tree actually goes so build_html() knows whether to render the control at all.
	 */
	private function render_branch( array $branch, $tag, $limit, &$counter, $depth, &$max_depth ) {
		if ( empty( $branch ) ) {
			return '';
		}

		$depth_attr = $depth > 0 ? ' data-depth="' . (int) $depth . '"' : '';
		if ( $depth > $max_depth ) {
			$max_depth = $depth;
		}

		$html = "<{$tag} class=\"mavo-toc__list\"{$depth_attr}>";

		foreach ( $branch as $node ) {
			++$counter;
			$hidden = ( $limit > 0 && $counter > $limit ) ? ' mavo-toc__item--hidden' : '';
			$html  .= '<li class="mavo-toc__item' . $hidden . '">';
			$html  .= '<a href="#' . esc_attr( $node['heading']['id'] ) . '">' . esc_html( $node['heading']['text'] ) . '</a>';
			$html  .= $this->render_branch( $node['children'], $tag, $limit, $counter, $depth + 1, $max_depth );
			$html  .= '</li>';
		}

		$html .= "</{$tag}>";

		return $html;
	}
}
