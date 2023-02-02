<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://www.lmr-hh.de
 * @since             1.0.0
 * @package           Mec_Ics
 *
 * @wordpress-plugin
 * Plugin Name:       ICS Feed for Modern Events Calendar
 * Plugin URI:        https://github.com/lmr-hh/mec-ics
 * Description:       Exposes events from the modern events calendar as an ICS feed.
 * Version:           1.0.0
 * Author:            Landesmusikrat Hamburg
 * Author URI:        https://www.lmr-hh.de
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       mec-ics
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

require __DIR__ . '/vendor/autoload.php';

\LMR\MecIcs\Plugin::initialize();
