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
			'markers'             => __( 'Show bullets/numbers', 'mavo-toc' ),
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
			'markers'             => ! empty( $input['markers'] ),
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

			<hr />

			<?php $this->render_shortcode_reference(); ?>
		</div>
		<?php
	}

	private function render_shortcode_reference() {
		$d = Mavo_TOC::get_options();

		$rows = array(
			array(
				'title',
				__( 'text', 'mavo-toc' ),
				$d['title'],
				__( 'Heading shown above the list. Empty string hides it for that instance.', 'mavo-toc' ),
			),
			array(
				'min_level',
				'1-6',
				(string) $d['min_level'],
				__( 'Shallowest heading level (H1-H6) to include.', 'mavo-toc' ),
			),
			array(
				'max_level',
				'1-6',
				(string) $d['max_level'],
				__( 'Deepest heading level to include. Raised automatically if lower than min_level.', 'mavo-toc' ),
			),
			array(
				'collapsible',
				'true / false',
				$d['collapsible'] ? 'true' : 'false',
				__( 'Adds a title button that shows/hides the whole list.', 'mavo-toc' ),
			),
			array(
				'collapsed',
				'true / false',
				$d['collapsed'] ? 'true' : 'false',
				__( 'Starts fully collapsed (title only). Has no effect unless collapsible is also true.', 'mavo-toc' ),
			),
			array(
				'sticky',
				'true / false',
				$d['sticky'] ? 'true' : 'false',
				__( 'Keeps the box in view while scrolling, pinned just below the "Sticky bar CSS selector" above, and auto-collapses to its title once actually pinned.', 'mavo-toc' ),
			),
			array(
				'numbered',
				'true / false',
				$d['numbered'] ? 'true' : 'false',
				__( 'Uses an ordered list (ol) instead of an unordered one (ul). Combine with markers to actually show the numbers.', 'mavo-toc' ),
			),
			array(
				'markers',
				'true / false',
				$d['markers'] ? 'true' : 'false',
				__( 'Shows the bullet (or number, with numbered) in front of each top-level entry. Nested sub-entries always show theirs.', 'mavo-toc' ),
			),
			array(
				'smooth_scroll',
				'true / false',
				$d['smooth_scroll'] ? 'true' : 'false',
				__( 'Smoothly scrolls to a heading on click instead of jumping instantly, landing below any fixed bars.', 'mavo-toc' ),
			),
			array(
				'limit',
				__( '0 or higher', 'mavo-toc' ),
				(string) $d['limit'],
				__( 'Max entries shown before a "Show more" toggle appears. 0 = no limit.', 'mavo-toc' ),
			),
			array(
				'class',
				__( 'text', 'mavo-toc' ),
				'',
				__( 'Extra CSS class added to the wrapper, for custom styling. No global default — shortcode-only.', 'mavo-toc' ),
			),
		);
		?>
		<h2><?php esc_html_e( 'Shortcode reference', 'mavo-toc' ); ?></h2>
		<p>
			<?php
			printf(
				/* translators: %s is the shortcode tag, e.g. [mavo_toc] */
				esc_html__( 'Place %s anywhere in a post or page. Every attribute below is optional and falls back to the matching setting above when omitted.', 'mavo-toc' ),
				'<code>[mavo_toc]</code>'
			);
			?>
		</p>
		<table class="widefat striped" style="max-width: 900px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Attribute', 'mavo-toc' ); ?></th>
					<th><?php esc_html_e( 'Accepts', 'mavo-toc' ); ?></th>
					<th><?php esc_html_e( 'Current default', 'mavo-toc' ); ?></th>
					<th><?php esc_html_e( 'Description', 'mavo-toc' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><code><?php echo esc_html( $row[0] ); ?></code></td>
						<td><?php echo esc_html( $row[1] ); ?></td>
						<td><code><?php echo '' === $row[2] ? esc_html__( '(empty)', 'mavo-toc' ) : esc_html( $row[2] ); ?></code></td>
						<td><?php echo esc_html( $row[3] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description">
			<?php
			printf(
				/* translators: %1$s is the exclude class name, %2$s is the settings field label */
				esc_html__( 'To skip a single heading from the list entirely, add the class %1$s to it in the editor (configurable as "%2$s" above).', 'mavo-toc' ),
				'<code>' . esc_html( $d['exclude_class'] ) . '</code>',
				esc_html__( 'Exclude class', 'mavo-toc' )
			);
			?>
		</p>

		<h3><?php esc_html_e( 'Examples', 'mavo-toc' ); ?></h3>
		<table class="widefat striped" style="max-width: 900px;">
			<tbody>
				<tr>
					<td style="width: 40%;"><code>[mavo_toc]</code></td>
					<td><?php esc_html_e( 'Uses every default above as-is.', 'mavo-toc' ); ?></td>
				</tr>
				<tr>
					<td><code>[mavo_toc title="In this article" min_level="2" max_level="3"]</code></td>
					<td><?php esc_html_e( 'Custom title, only H2 and H3 headings.', 'mavo-toc' ); ?></td>
				</tr>
				<tr>
					<td><code>[mavo_toc sticky="true" collapsible="true" collapsed="true"]</code></td>
					<td><?php esc_html_e( 'Floats and pins below the menu bar while scrolling, starting fully collapsed to its title.', 'mavo-toc' ); ?></td>
				</tr>
				<tr>
					<td><code>[mavo_toc numbered="true" markers="true" limit="8"]</code></td>
					<td><?php esc_html_e( 'Visibly numbered list (1. 2. 3. ...), showing only the first 8 entries with a "Show more" toggle for the rest.', 'mavo-toc' ); ?></td>
				</tr>
				<tr>
					<td><code>[mavo_toc title="" class="my-toc"]</code></td>
					<td><?php esc_html_e( 'No title shown, with an extra CSS class for custom styling.', 'mavo-toc' ); ?></td>
				</tr>
			</tbody>
		</table>
		<?php
	}
}
