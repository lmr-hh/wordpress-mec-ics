<?php
/**
 * The plugin bootstrap file. This file is automatically modified during a release to include the
 * correct version number.
 *
 * @link              https://www.lmr-hh.de
 * @since             1.0.0
 * @package           mec-ics
 * @author            Landesmusikrat Hamburg
 * @license           MIT
 *
 * @wordpress-plugin
 * Plugin Name:       ICS Feed for Modern Events Calendar
 * Plugin URI:        https://github.com/lmr-hh/mec-ics
 * Description:       Publishes an ICS feed containing events from Modern Events Calendar.
 * {{PLUGIN_VERSION}}
 * Author:            Landesmusikrat Hamburg
 * Author URI:        https://www.lmr-hh.de
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       mec-ics
 * Domain Path:       /languages
 *
 * Requires at least: 5.2
 * Requires PHP:      7.4
 */

declare( strict_types=1 );

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require __DIR__ . '/vendor/autoload.php';

$mec_ics_plugin_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	'https://github.com/lmr-hh/wordpress-mec-ics/',
	__FILE__,
	'mec-ics'
);
$mec_ics_plugin_update_checker->getVcsApi()->enableReleaseAssets();
\LMR\MecIcs\Plugin::initialize();
