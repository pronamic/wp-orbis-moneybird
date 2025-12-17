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
	function () {
		WP_CLI::add_command(
			'orbis-moneybird import-sales-invoices',
			function ( $args,  $assoc_args ) {
				$assoc_args = wp_parse_args(
					$assoc_args,
					[
						'page'   => 1,
						'period' => 'this_year',
					]
				);

				/**
				 * Moneybird client.
				 */
				$authorization_id  = (int) \get_option( 'pronamic_moneybird_authorization_post_id' );
				$administration_id = ( 0 === $authorization_id ) ? 0 : (int) \get_post_meta( $authorization_id, '_pronamic_moneybird_administration_id', true );

				$api_token = \get_post_meta( $authorization_id, '_pronamic_moneybird_api_token', true );

				$page   = $assoc_args['page'];
				$period = $assoc_args['period'];

				while ( true ) {
					WP_CLI::log( 'Page: ' . $page );

					$url = \add_query_arg(
						[
							'page'   => $page,
							'period' => $period,
						],
						'https://moneybird.com/api/v2/' . $administration_id . '/sales_invoices.json'
					);

					$response = \wp_remote_get(
						$url,
						[
							'headers' => [
								'Authorization' => 'Bearer ' . $api_token,
							],
						]
					);

					$response_code = \wp_remote_retrieve_response_code( $response );

					if ( 200 !== $response_code ) {
						WP_CLI::error( 'Could not retrieve sales invoices from Moneybird.' );
					}

					$body = \wp_remote_retrieve_body( $response );

					$sales_invoices = \json_decode( $body );

					if ( empty( $sales_invoices ) ) {
						break;
					}

					global $wpdb;

					foreach ( $sales_invoices as $sales_invoice ) {
						WP_CLI::log( 'Invoice: ' . $sales_invoice->id );

						$invoice_number = $sales_invoice->id;

						$invoice_data = [
							'host'              => 'moneybird.com',
							'id'                => $sales_invoice->id,
							'administration_id' => $sales_invoice->administration_id,
							'contact_id'        => $sales_invoice->contact_id,
							'draft_id'          => $sales_invoice->draft_id ?? null,
						];

						$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $wpdb->orbis_invoices WHERE invoice_number = %s", $invoice_number ) );

						$data = [
							'invoice_date'    => $sales_invoice->invoice_date,
							'invoice_number'  => $invoice_number,
							'invoice_data'    => \wp_json_encode( $invoice_data ),
							'amount'          => $sales_invoice->total_price_excl_tax_base,
							'external_source' => 'moneybird',
							'external_id'     => $sales_invoice->id,
							'moneybird_json'  => \wp_json_encode( $sales_invoice ),
						];

						$format = [
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
							'%s',
						];

						if ( $exists ) {
							$result = $wpdb->update(
								$wpdb->orbis_invoices,
								$data,
								[ 'id' => $exists ],
								$format,
								[ '%d' ]
							);

							if ( false === $result ) {
								WP_CLI::warning( 'Could not update invoice ' . $invoice_number );
							} else {
								WP_CLI::success( 'Updated invoice ' . $invoice_number );
							}
						} else {
							$data['created_at'] = \gmdate( 'Y-m-d H:i:s' );
							$format[]           = '%s';

							$result = $wpdb->insert(
								$wpdb->orbis_invoices,
								$data,
								$format
							);

							if ( false === $result ) {
								WP_CLI::warning( 'Could not insert invoice ' . $invoice_number );
							} else {
								WP_CLI::success( 'Imported invoice ' . $invoice_number );
							}
						}

						WP_CLI::log( 'Sleep 1 second' );

						\sleep( 1 );
					}

					$page++;
				}
			}
		);
	}
);
