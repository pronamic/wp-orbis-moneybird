<?php
/**
 * CLI
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2025 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Moneybird
 */

namespace Pronamic\Orbis\Moneybird;

use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\add_action(
    'cli_init',
    function() {
        WP_CLI::add_command(
            'orbis-moneybird import-sales-invoices',
            function() {
                /**
                 * Moneybird client.
                 */
                $authorization_id  = (int) \get_option( 'pronamic_moneybird_authorization_post_id' );
                $administration_id = ( 0 === $authorization_id ) ? 0 : (int) \get_post_meta( $authorization_id, '_pronamic_moneybird_administration_id', true );

                $api_token = \get_post_meta( $authorization_id, '_pronamic_moneybird_api_token', true );

                WP_CLI::log( $api_token );
            }
        );
    }
);
