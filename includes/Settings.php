<?php
/**
 * This file contains the `Settings` class rendering the settings page of the plugin.
 *
 * @package mec-ics
 */

declare( strict_types=1 );

namespace LMR\MecIcs;

/**
 * This class implements the settings page of the Modern Events Calendar ICS plugin.
 */
class Settings {

	const DEFAULT_PRODUCT_ID = '-//Landesmusikrat Hamburg//MEC-ICS//DE';

	/**
	 * Create and initialize a new <code>Settings</code> instance. This will also register the
	 * required hooks to display the page.
	 */
	public function __construct() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'add_main_menu' ], 20 );
	}

	/**
	 * Registers the plugin's settings and settings pages.
	 *
	 * @return void
	 */
	public function register_settings() {
		// Add settings sections.
		add_settings_section(
			'mec-ics-section-feed',
			esc_html__( 'Feed Settings', 'mec-ics' ),
			[ $this, 'render_section' ],
			'mec-ics-settings-page',
			[
				'description' => __(
					'The ICS feed contains the events you created in Modern Events Calendar.',
					'mec-ics'
				),
			]
		);
		add_settings_section(
			'mec-ics-section-advanced',
			esc_html__( 'Advanced', 'mec-ics' ),
			[ $this, 'render_section' ],
			'mec-ics-settings-page',
			[
				'description' => __(
					"In this section you can configure some of the technical details that 
typically won't be seed by the end users.",
					'mec-ics'
				),
			]
		);

		// Add settings fields to the general section.
		add_settings_field(
			'mec-ics-feed-slug',
			esc_html__( 'Feed Slug', 'mec-ics' ),
			[ $this, 'render_feed_slug_settings_field' ],
			'mec-ics-settings-page',
			'mec-ics-section-feed',
			[ 'label_for' => 'mec-ics-feed-slug' ]
		);
		add_settings_field(
			'mec-ics-feed-name',
			esc_html__( 'Feed Name', 'mec-ics' ),
			[ $this, 'render_feed_name_settings_field' ],
			'mec-ics-settings-page',
			'mec-ics-section-feed',
			[ 'label_for' => 'mec-ics-feed-name' ]
		);
		add_settings_field(
			'mec-ics-private-events',
			esc_html__( 'Private Events', 'mec-ics' ),
			[ $this, 'render_private_events_field' ],
			'mec-ics-settings-page',
			'mec-ics-section-feed',
			[ 'label_for' => 'mec-ics-private-events' ]
		);
		add_settings_field(
			'mec-ics-interval',
			esc_html__( 'Time Interval', 'mec-ics' ),
			[ $this, 'render_time_interval_field' ],
			'mec-ics-settings-page',
			'mec-ics-section-feed'
		);
		add_settings_field(
			'mec-ics-event-limit',
			esc_html__( 'Event Limit', 'mec-ics' ),
			[ $this, 'render_event_limit_field' ],
			'mec-ics-settings-page',
			'mec-ics-section-feed',
			[ 'label_for' => 'mec-ics-event-limit' ]
		);

		// Add settings fields to the advanced section.
		add_settings_field(
			'mec-ics-prodid',
			esc_html__( 'Product Identifier', 'mec-ics' ),
			[ $this, 'render_prod_id_field' ],
			'mec-ics-settings-page',
			'mec-ics-section-advanced',
			[ 'label_for' => 'mec-ics-prodid' ]
		);
		add_settings_field(
			'mec-ics-uid-format',
			esc_html__( 'Event UID Format', 'mec-ics' ),
			[ $this, 'render_uid_format_field' ],
			'mec-ics-settings-page',
			'mec-ics-section-advanced',
			[ 'label_for' => 'mec-ics-uid-format' ]
		);

		// Register settings with WordPress.
		register_setting(
			'mec-ics-settings-page',
			'mec-ics-feed-slug',
			[ 'type' => 'string' ]
		);
		register_setting(
			'mec-ics-settings-page',
			'mec-ics-feed-name',
			[
				'type'    => 'string',
				'default' => get_bloginfo( 'name' ),
			]
		);
		register_setting(
			'mec-ics-settings-page',
			'mec-ics-private-events',
			[
				'type'    => 'boolean',
				'default' => false,
			]
		);
		register_setting(
			'mec-ics-settings-page',
			'mec-ics-past-events',
			[
				'type'              => 'integer',
				'default'           => 90,
				'sanitize_callback' => [ $this, 'sanitize_past_events' ],
			]
		);
		register_setting(
			'mec-ics-settings-page',
			'mec-ics-future-events',
			[
				'type'              => 'integer',
				'default'           => 365,
				'sanitize_callback' => [ $this, 'sanitize_future_events' ],
			]
		);
		register_setting(
			'mec-ics-settings-page',
			'mec-ics-event-limit',
			[
				'type'              => 'integer',
				'sanitize_callback' => [ $this, 'sanitize_event_limit' ],
			]
		);
		register_setting(
			'mec-ics-settings-page',
			'mec-ics-prodid',
			[
				'type'    => 'string',
				'default' => self::DEFAULT_PRODUCT_ID,
			]
		);
		register_setting(
			'mec-ics-settings-page',
			'mec-ics-uid-format',
			[
				'type'    => 'string',
				'default' => '%d@' . wp_unslash( $_SERVER['SERVER_NAME'] ), // phpcs:ignore
			]
		);
	}

	/**
	 * Renders the description of a settings section.
	 *
	 * @param array $args An array of settings. Currently, the only used setting is 'description'.
	 *
	 * @return void
	 */
	public function render_section( array $args ) {
		echo '<p>' . esc_html( $args['description'] ) . '</p>';
	}

	/**
	 * Renders the settings field for the feed slug.
	 *
	 * @return void
	 */
	public function render_feed_slug_settings_field() {
		$permalink_structure = get_option( 'permalink_structure' );
		$feed                = get_option( 'mec-ics-feed-slug' );
		$feed_url            = '';
		if ( $feed ) {
			if ( $permalink_structure ) {
				$feed_url = get_site_url( null, '/feed/' . rawurlencode( $feed ) );
			} else {
				$feed_url = get_site_url( null, '/?feed=' . rawurlencode( $feed ) );
			}
		} ?>
		<input
				type="text" class="regular-text" id="mec-ics-feed-slug" name="mec-ics-feed-slug"
				value="<?php echo esc_attr( $feed ); ?>">
		<p class="description">
			<?php
			__(
				'The ICS feed is published as a WordPress feed. The slug of the feed determines
the URL at which the feed will be found.<br /><b>Attention</b>: If you use the slug of a default 
feed (rss, rss2, rdf, atom) the ICS feed will replace the default feed.',
				'mec-ics'
			);
			?>
		</p>
		<p>
			<?php
			if ( $feed_url ) {
				printf(
					wp_kses(
					// translators: placeholder will be replaced by the feed URL.
						__( 'The ICS feed currently available at <code>%s</code>', 'mec-ics' ),
						[ 'code' => [] ]
					),
					esc_html( $feed_url )
				);
			} else {
				esc_html_e(
					'Currently the ICS feed is not configured. Enter a feed slug to enable the 
feed.',
					'mec-ics'
				);
			}
			?>
		</p>
		<?php
	}

	/**
	 * Renders the settings field for the feed name.
	 *
	 * @return void
	 */
	public function render_feed_name_settings_field() {
		?>
		<input
				type="text" class="regular-text" id="mec-ics-feed-name" name="mec-ics-feed-name"
				value="<?php echo esc_attr( get_option( 'mec-ics-feed-name' ) ); ?>">
		<p class="description">
			<?php
			esc_html_e(
				'The name of the feed is typically show as the default calendar name when a
user subscribes to the feed via URL.',
				'mec-ics'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Renders the checkbox for the inclusion of private events.
	 *
	 * @return void
	 */
	public function render_private_events_field() {
		?>
		<label for="mec-ics-private-events">
			<input type="checkbox" id="mec-ics-private-events" name="mec-ics-private-events"
				<?php echo get_option( 'mec-ics-private-events' ) ? 'checked' : ''; ?>
			>
			<?php esc_html_e( 'Include Private Events in Feed', 'mec-ics' ); ?>
		</label>
		<p class="description">
			<?php
			echo wp_kses(
				__(
					'If checked events set to private will be included in the ICS feed. The 
events will have their classification to <code>PRIVATE</code>.',
					'mec-ics'
				),
				[ 'code' => [] ]
			);
			?>
		</p>
		<?php
	}

	/**
	 * Renders the two fields that allow configuring the time interval.
	 *
	 * @return void
	 */
	public function render_time_interval_field() {
		?>
		<label for="mec-ics-past-events">
			<?php esc_html_e( 'Days before today:', 'mec-ics' ); ?>
		</label>
		<input
				type="number" min="1" step="1" id="mec-ics-past-events" name="mec-ics-past-events"
				value="<?php echo esc_attr( get_option( 'mec-ics-past-events' ) ); ?>">
		<label for="mec-ics-future-events">
			<?php esc_html_e( 'Days after today:', 'mec-ics' ); ?>
		</label>
		<input
				type="number" min="1" step="1" id="mec-ics-future-events"
				name="mec-ics-future-events"
				value="<?php echo esc_attr( get_option( 'mec-ics-future-events' ) ); ?>">
		<p class="description">
			<?php
			esc_html_e(
				'By default only events within a specific time interval are included in the
ICS feed. You can configure the time interval. You can remove either or both intervals to include 
all past or future events. In order to reduce database load it is recommended to set a time 
interval that is large enough for your needs. By default events are included if their date is at 
most 90 days in the past and 365 days in the future.',
				'mec-ics'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Sanitizes the past events value.
	 *
	 * @param mixed|int $value A potential integer.
	 *
	 * @return int|string
	 */
	public function sanitize_past_events( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		$i = intval( $value );
		if ( $i != $value || $i < 1 ) { // phpcs:ignore
			add_settings_error(
				'mec-ics-messages',
				'mec-ics-message-invalid-past-events',
				__( 'The number of past days must be a positive number.', 'mec-ics' )
			);

			return '';
		}

		return $i;
	}

	/**
	 * Sanitizes the future events value.
	 *
	 * @param mixed|int $value A potential integer.
	 *
	 * @return int|string
	 */
	public function sanitize_future_events( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		$i = intval( $value );
		if ( $i != $value || $i < 1 ) { // phpcs:ignore
			add_settings_error(
				'mec-ics-messages',
				'mec-ics-message-invalid-future-events',
				__( 'The number of future days must be a positive number.', 'mec-ics' )
			);

			return '';
		}

		return $i;
	}

	/**
	 * Renders the settings field for the total event limit.
	 *
	 * @return void
	 */
	public function render_event_limit_field() {
		?>
		<input
				type="number" min="1" step="1" class="regular-text" id="mec-ics-event-limit"
				name="mec-ics-event-limit"
				value="<?php echo esc_attr( get_option( 'mec-ics-event-limit' ) ); ?>">
		<p class="description">
			<?php
			echo wp_kses(
				__(
					"The maximum number of events included in the calendar at any time. This should 
be sufficiently high to include all upcoming events as clients usually do not support pagination. 
Set to <code>0</code> or leave empty if you don't want to enforce a limit. The recommended approach 
to limit the number of events is to use the time interval setting above.",
					'mec-ics'
				),
				[ 'code' => [] ],
			);
			?>
		</p>
		<?php
	}

	/**
	 * Sanitizes the event limit value.
	 *
	 * @param mixed|int $value A potential integer.
	 *
	 * @return int|string
	 */
	public function sanitize_event_limit( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		$i = intval( $value );
		if ( $i != $value || $i < 1 ) { // phpcs:ignore
			add_settings_error(
				'mec-ics-messages',
				'mec-ics-message-invalid-event-limit',
				__( 'The event limit must be a positive number.', 'mec-ics' )
			);

			return '';
		}

		return $i;
	}

	/**
	 * Renders the product identifier settings field.
	 *
	 * @return void
	 */
	public function render_prod_id_field() {
		?>
		<input
				type="text" class="regular-text" id="mec-ics-prodid" name="mec-ics-prodid"
				value="<?php echo esc_attr( get_option( 'mec-ics-prodid' ) ); ?>">
		<p class="description">
			<?php
			echo wp_kses(
				__(
					'You can supply your own product ID. This is typically a 
<a href="https://en.wikipedia.org/wiki/Formal_Public_Identifier" target="_blank">FPI value</a> 
but can technically be any string.',
					'mec-ics'
				),
				[ 'a' => [ 'href' ] ],
			);
			?>
		</p>
		<?php
	}

	/**
	 * Renders teh UID format settings field.
	 *
	 * @return void
	 */
	public function render_uid_format_field() {
		?>
		<input
				type="text" class="regular-text" id="mec-ics-uid-format" name="mec-ics-uid-format"
				value="<?php echo esc_attr( get_option( 'mec-ics-uid-format' ) ); ?>">
		<p class="description">
			<?php
			echo wp_kses(
			// translators: Literal value. Not substituted by anything.
				__(
					'The format must contain the <code>%d</code> format specified which will be 
replaced by the ID of the respective event. It is not required that the UID has an email-like 
format.',
					'mec-ics'
				),
				[ 'code' => [] ],
			);
			?>
		</p>
		<?php if ( ! str_contains( get_option( 'mec-ics-uid-format' ), '%d' ) ) : ?>
			<p class="notice notice-warning">
				<?php
				esc_html_e(
					'It looks like the event UID does not contain a format specifier. If all 
events have the same UID it may confuse some ICS clients.',
					'mec-ics'
				);
				?>
			</p>
		<?php endif ?>
		<?php
	}

	/**
	 * Adds the ICS Feed submenu to the Modern Events Calendar menu.
	 *
	 * @return void
	 */
	public function add_main_menu() {
		add_submenu_page(
			'mec-intro',
			__( 'ICS Feed Settings', 'mec-ics' ),
			__( 'ICS Feed', 'mec-ics' ),
			'manage_options',
			'mec-ics-settings',
			[ $this, 'render_settings_page' ],
			10
		);
	}

	/**
	 * Renders the settings page for the plugin.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if (
			isset( $_GET['settings-updated'] ) && // phpcs:ignore
			empty( get_settings_errors( 'mec-ics-messages' ) )
		) {
			add_settings_error(
				'mec-ics-messages',
				'mec-ics-message-permalink',
				__(
					'Settings Saved. You might need to save your permalink structure in order for
the feed to become available.',
					'mec-ics'
				),
				'success'
			);

		}
		settings_errors( 'mec-ics-messages' );
		?>
		<div class="wrap">
			<div id="icon-themes" class="icon32"></div>
			<h2><?php esc_html_e( 'ICS Feed Settings', 'mec-ics' ); ?></h2>
			<form method="POST" action="options.php">
				<?php
				settings_fields( 'mec-ics-settings-page' );
				do_settings_sections( 'mec-ics-settings-page' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
