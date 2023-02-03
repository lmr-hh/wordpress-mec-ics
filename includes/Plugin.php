<?php
/**
 * The main plugin file. This is the plugin entrypoint.
 *
 * @package mec-ics
 */

declare( strict_types=1 );

namespace LMR\MecIcs;

use Exception;

/**
 * The 'ICS Feed for Modern Events Calender' plugin. This singleton class is the main entrypoint of
 * the plugin.
 */
class Plugin {

	/**
	 * The singleton instance of the plugin.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Initializes the plugin.
	 *
	 * @return void
	 * @throws Exception If the plugin is already initialized.
	 */
	public static function initialize() {
		if ( null !== self::$instance ) {
			throw new Exception( 'MecIcsPlugin is already initialized.' );
		}
		self::$instance = new self();
	}

	/**
	 * Returns the plugin instance. This is <code>null</code> until {@link initialize} has been
	 * called.
	 *
	 * @return Plugin|null The plugin instance.
	 */
	public static function get_instance(): Plugin {
		return self::$instance;
	}

	/**
	 * Cloning singletons is not allowed.
	 */
	private function __clone() {
	}

	/**
	 * Unserializating singletons is not allowed.
	 *
	 * @throws Exception Always.
	 */
	public function __wakeup() {
		throw new Exception( 'Cannot unserialize singleton' );
	}

	/**
	 * Creates a new plugin instance.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'load_i18n' ] );
		add_action( 'plugins_loaded', [ $this, 'check_dependencies' ] );
		$settings = new Settings();
		$feed     = new Feed();
	}

	/**
	 * Loads the plugin's text domain.
	 *
	 * @return void
	 */
	public function load_i18n() {
		load_plugin_textdomain(
			'mec-ics',
			false,
			dirname( plugin_basename( __FILE__ ), 2 ) . '/languages/'
		);
	}

	/**
	 * Validates that the required dependencies are present, specifically an active installation of
	 * Modern Events Calendar. If not all dependencies are met, an admin notice is shown.
	 *
	 * @return void
	 */
	public function check_dependencies() {
		if ( ! class_exists( '\\MEC\\Events\\EventsQuery' ) ) {
			add_action( 'admin_notices', [ $this, 'render_dependency_error' ] );
		}
	}

	/**
	 * Renders an error message that not all dependencies are present.
	 *
	 * @return void
	 */
	public function render_dependency_error() { ?>
		<div class="notice notice-error">
			<p>
				<?php
				wp_kses(
					__(
						'Modern Events Calendar plugin not found. The plugin <b>ICS for Modern
Events Calendar</b> requires an active installation of the Modern Events Calendar plugin.',
						'mec-ics'
					),
					[ 'b' => [] ],
				);
				?>
			</p>
		</div>
		<?php
	}
}
