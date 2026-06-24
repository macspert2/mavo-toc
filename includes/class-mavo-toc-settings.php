<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Mavo_TOC_Settings {

	const PAGE_SLUG = 'mavo-toc-settings';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_menu() {
		add_options_page(
			__( 'Mavo TOC', 'mavo-toc' ),
			__( 'Mavo TOC', 'mavo-toc' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting( self::PAGE_SLUG, Mavo_TOC::OPTION_KEY, array( $this, 'sanitize' ) );

		add_settings_section( 'mavo_toc_main', '', '__return_false', self::PAGE_SLUG );

		$fields = array(
			'title'               => __( 'Default title', 'mavo-toc' ),
			'min_level'           => __( 'Minimum heading level', 'mavo-toc' ),
			'max_level'           => __( 'Maximum heading level', 'mavo-toc' ),
			'collapsible'         => __( 'Collapsible', 'mavo-toc' ),
			'collapsed'           => __( 'Collapsed by default', 'mavo-toc' ),
			'sticky'              => __( 'Sticky while scrolling', 'mavo-toc' ),
			'numbered'            => __( 'Numbered list', 'mavo-toc' ),
			'smooth_scroll'       => __( 'Smooth scroll to heading', 'mavo-toc' ),
			'limit'               => __( 'Limit before "Show more"', 'mavo-toc' ),
			'exclude_class'       => __( 'Exclude class', 'mavo-toc' ),
			'sticky_bar_selector' => __( 'Sticky bar CSS selector', 'mavo-toc' ),
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_field' ),
				self::PAGE_SLUG,
				'mavo_toc_main',
				array( 'key' => $key )
			);
		}
	}

	public function sanitize( $input ) {
		$defaults = Mavo_TOC::get_defaults();
		$input    = is_array( $input ) ? $input : array();

		$min = isset( $input['min_level'] ) ? max( 1, min( 6, (int) $input['min_level'] ) ) : $defaults['min_level'];
		$max = isset( $input['max_level'] ) ? max( 1, min( 6, (int) $input['max_level'] ) ) : $defaults['max_level'];

		return array(
			'title'               => isset( $input['title'] ) ? sanitize_text_field( $input['title'] ) : $defaults['title'],
			'min_level'           => $min,
			'max_level'           => max( $min, $max ),
			'collapsible'         => ! empty( $input['collapsible'] ),
			'collapsed'           => ! empty( $input['collapsed'] ),
			'sticky'              => ! empty( $input['sticky'] ),
			'numbered'            => ! empty( $input['numbered'] ),
			'smooth_scroll'       => ! empty( $input['smooth_scroll'] ),
			'limit'               => isset( $input['limit'] ) ? max( 0, (int) $input['limit'] ) : $defaults['limit'],
			'exclude_class'       => isset( $input['exclude_class'] ) ? sanitize_html_class( $input['exclude_class'] ) : $defaults['exclude_class'],
			'sticky_bar_selector' => isset( $input['sticky_bar_selector'] ) ? sanitize_text_field( $input['sticky_bar_selector'] ) : $defaults['sticky_bar_selector'],
		);
	}

	public function render_field( $args ) {
		$options = Mavo_TOC::get_options();
		$key     = $args['key'];
		$value   = $options[ $key ];
		$name    = Mavo_TOC::OPTION_KEY . '[' . $key . ']';

		switch ( $key ) {
			case 'min_level':
			case 'max_level':
				echo '<select name="' . esc_attr( $name ) . '">';
				for ( $i = 1; $i <= 6; $i++ ) {
					printf( '<option value="%1$d" %2$s>H%1$d</option>', $i, selected( $value, $i, false ) );
				}
				echo '</select>';
				break;

			case 'limit':
				printf(
					'<input type="number" min="0" step="1" name="%s" value="%s" class="small-text" /> <p class="description">%s</p>',
					esc_attr( $name ),
					esc_attr( $value ),
					esc_html__( '0 = no limit', 'mavo-toc' )
				);
				break;

			case 'title':
				// Shows only the actually saved override, never the resolved
				// default — pre-filling with a resolved value would silently
				// freeze whatever language was active in wp-admin right now as
				// a permanent override the next time the form is saved.
				$saved = get_option( Mavo_TOC::OPTION_KEY, array() );
				printf(
					'<input type="text" name="%s" value="%s" placeholder="%s" class="regular-text" /> <p class="description">%s</p>',
					esc_attr( $name ),
					esc_attr( is_array( $saved ) && isset( $saved['title'] ) ? $saved['title'] : '' ),
					esc_attr( Mavo_TOC::get_defaults()['title'] ),
					esc_html__( 'Leave empty to use the default title, shown in whichever language Polylang is currently serving. Only set this if you want the same fixed text on every language.', 'mavo-toc' )
				);
				break;

			case 'exclude_class':
				printf( '<input type="text" name="%s" value="%s" class="regular-text" />', esc_attr( $name ), esc_attr( $value ) );
				break;

			case 'sticky_bar_selector':
				printf(
					'<input type="text" name="%s" value="%s" class="regular-text" /> <p class="description">%s</p>',
					esc_attr( $name ),
					esc_attr( $value ),
					esc_html__( 'CSS selector of the site\'s fixed/sticky menu bar. A sticky TOC will stick just below it instead of underneath it.', 'mavo-toc' )
				);
				break;

			default:
				printf(
					'<label><input type="checkbox" name="%s" value="1" %s /> %s</label>',
					esc_attr( $name ),
					checked( $value, true, false ),
					esc_html__( 'Enabled', 'mavo-toc' )
				);
				break;
		}
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Mavo TOC Settings', 'mavo-toc' ); ?></h1>
			<p>
				<?php
				esc_html_e( 'Default values used by the [mavo_toc] shortcode. Any of these can be overridden per shortcode, e.g.', 'mavo-toc' );
				?>
				<code>[mavo_toc title="Contents" sticky="true"]</code>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( self::PAGE_SLUG );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
