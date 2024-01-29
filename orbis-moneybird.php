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
		/**
		 * Orbis Companies.
		 * 
		 * @link https://github.com/pronamic/wp-orbis-companies/commit/7da50a62313b77de7dc831f854b0fd08b6063d7f
		 * @link https://github.com/pronamic/wp-pronamic-moneybird
		 */
		if ( post_type_exists( 'orbis_company' ) ) {
			add_post_type_support( 'orbis_company', 'pronamic_moneybird_contact' );
		}

		/**
		 * Orbis Subscriptions.
		 * 
		 * @link https://github.com/pronamic/wp-orbis-subscriptions/commit/5f0606c6f4ac01dea3bbdd0e25f058eaaec9a82d
		 * @link https://github.com/pronamic/wp-pronamic-moneybird
		 */
		if ( post_type_exists( 'orbis_subs_product' ) ) {
			add_post_type_support( 'orbis_subs_product', 'pronamic_moneybird_product' );
		}
	},
	200
);

add_action(
	'orbis_after_side_content',
	function () {
		if ( ! is_singular() ) {
			return;
		}

		if ( ! post_type_supports( get_post_field( 'post_type' ), 'pronamic_moneybird_contact' ) ) {
			return;
		}

		$contact_id = get_post_meta( get_post_field( 'ID' ), '_pronamic_moneybird_contact_id', true );

		if ( '' === $contact_id ) {
			return;
		}

		include __DIR__ . '/templates/contact-card.php';
	}
);
