<?php
/**
 * Orbis Moneybird
 *
 * @package   Pronamic\Orbis\Moneybird
 * @author    Pronamic
 * @copyright 2024 Pronamic
 * @license   GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Orbis Moneybird
 * Plugin URI:        https://wp.pronamic.directory/plugins/orbis-moneybird/
 * Description:       This WordPress plugin provides the link between Orbis and your Moneybird administration.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      8.0
 * Author:            Pronamic
 * Author URI:        https://www.pronamic.eu/
 * Text Domain:       orbis-moneybird
 * Domain Path:       /languages/
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://wp.pronamic.directory/plugins/orbis-moneybird/
 * GitHub URI:        https://github.com/pronamic/orbis-moneybird
 */

add_action(
	'init',
	function () {
		if ( post_type_exists( 'orbis_company' ) ) {
			add_post_type_support( 'orbis_company', 'pronamic_moneybird_contact' );
		}
	},
	200
);
