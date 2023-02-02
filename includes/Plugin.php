<?php

namespace LMR\MecIcs;

/**
 * The 'ICS Feed for Modern Events Calender' plugin. This singleton class is the main entrypoint of the plugin.
 */
class Plugin {

	/**
	 * @var Plugin|null The plugin instance.
	 */
	private static ?Plugin $instance = null;

	/**
	 * Initializes the plugin.
	 *
	 * @return void
	 */
	public static function initialize() {
		if ( self::$instance !== null ) {
			throw new Exception( "MecIcsPlugin is already initialized." );
		}
		self::$instance = new self();
	}

	/**
	 * Returns the plugin instance. This is <code>null</code> until {@link initialize} has been called.
	 *
	 * @return Plugin|null The plugin instance.
	 */
	public static function getInstance() {
		return self::$instance;
	}

	/**
	 * Cloning singletons is not allowed.
	 */
	private function __clone() {
	}

	/**
	 * Unserializating singletons is not allowed.
	 */
	public function __wakeup() {
		throw new Exception( "Cannot unserialize singleton" );
	}

	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'loadI18n' ] );
		add_action( 'plugins_loaded', [ $this, 'checkDependencies' ] );
		$settings = new Settings();
		$feed     = new Feed();
	}

	/**
	 * Loads the plugin's text domain.
	 *
	 * @return void
	 */
	function loadI18n() {
		load_plugin_textdomain(
			'mec-ics',
			false,
			dirname( plugin_basename( __FILE__ ), 2 ) . '/languages/'
		);
	}

	/**
	 * Validates that the required dependencies are present, specifically an active installation of Modern Events
	 * Calendar. If not all dependencies are met, an admin notice is shown.
	 *
	 * @return void
	 */
	public function checkDependencies() {
		if ( ! class_exists( '\\MEC\\Events\\EventsQuery' ) ) {
			add_action( 'admin_notices', [ $this, 'renderDependencyError' ] );
		}
	}

	/**
	 * Renders an error message that not all dependencies are present.
	 *
	 * @return void
	 */
	public function renderDependencyError() { ?>
        <div class="notice notice-error">
            <p><?php _e( 'Modern Events Calendar plugin not found. The plugin <b>ICS for Modern Events Calendar</b> requires an active installation of the Modern Events Calendar plugin.', 'mec-ics' ); ?></p>
        </div>
	<?php }
}
