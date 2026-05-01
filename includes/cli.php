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
							'filter' => 'period:' . $period,
						],
						'https://moneybird.com/api/v2/' . $administration_id . '/sales_invoices.json'
					);

					WP_CLI::log( 'Moneybird API URL: ' . $url );

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

		WP_CLI::add_command(
			'orbis-moneybird process-sales-invoice',
			function ( $args, $assoc_args ) {
				if ( ! \function_exists( '\\orbis_moneybird_process_sales_invoice_projects' ) ) {
					WP_CLI::error( 'Function orbis_moneybird_process_sales_invoice_projects does not exist.' );
				}

				if ( ! \function_exists( '\\orbis_moneybird_process_sales_invoice_subscriptions' ) ) {
					WP_CLI::error( 'Function orbis_moneybird_process_sales_invoice_subscriptions does not exist.' );
				}

				$authorization_id = \array_key_exists( 'authorization_id', $assoc_args )
					? (int) $assoc_args['authorization_id']
					: (int) \get_option( 'pronamic_moneybird_authorization_post_id' );

				if ( 0 === $authorization_id ) {
					WP_CLI::error( 'Could not determine authorization post ID, provide --authorization_id or configure pronamic_moneybird_authorization_post_id.' );
				}

				$api_token         = \get_post_meta( $authorization_id, '_pronamic_moneybird_api_token', true );
				$administration_id = \get_post_meta( $authorization_id, '_pronamic_moneybird_administration_id', true );

				if ( '' === $api_token ) {
					WP_CLI::error( 'Could not retrieve API token for authorization post ID: ' . $authorization_id );
				}

				if ( '' === $administration_id ) {
					WP_CLI::error( 'Could not retrieve administration ID for authorization post ID: ' . $authorization_id );
				}

				$has_id        = \array_key_exists( 'id', $assoc_args );
				$has_reference = \array_key_exists( 'reference', $assoc_args );

				if ( $has_id && $has_reference ) {
					WP_CLI::error( 'Please provide either --id or --reference, not both.' );
				}

				if ( ! $has_id && ! $has_reference ) {
					WP_CLI::error( 'Please provide either --id or --reference.' );
				}

				if ( $has_id ) {
					$url = \sprintf(
						'https://moneybird.com/api/v2/%s/sales_invoices/%s.json',
						$administration_id,
						$assoc_args['id']
					);
				} else {
					$url = \sprintf(
						'https://moneybird.com/api/v2/%s/sales_invoices/find_by_reference/%s.json',
						$administration_id,
						\rawurlencode( $assoc_args['reference'] )
					);
				}

				WP_CLI::log( 'Moneybird API URL: ' . $url );

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
					WP_CLI::error( 'Could not retrieve sales invoice from Moneybird. Response code: ' . $response_code );
				}

				$body = \wp_remote_retrieve_body( $response );

				$sales_invoice = \json_decode( $body );

				if ( ! \is_object( $sales_invoice ) ) {
					WP_CLI::error( 'Invalid JSON response from Moneybird.' );
				}

				WP_CLI::log( 'Sales invoice ID: ' . $sales_invoice->id );
				WP_CLI::log( 'Invoice ID: ' . ( $sales_invoice->invoice_id ?? '(draft)' ) );
				WP_CLI::log( 'Reference: ' . ( $sales_invoice->reference ?? '' ) );

				\orbis_moneybird_process_sales_invoice_projects( $sales_invoice );
				\orbis_moneybird_process_sales_invoice_subscriptions( $sales_invoice );

				WP_CLI::success( 'Processed sales invoice: ' . $sales_invoice->id );
			},
			[
				'synopsis' => [
					[
						'type'        => 'assoc',
						'name'        => 'authorization_id',
						'description' => 'The authorization post ID for retrieving the Moneybird API token.',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'id',
						'description' => 'The Moneybird sales invoice ID.',
						'optional'    => true,
					],
					[
						'type'        => 'assoc',
						'name'        => 'reference',
						'description' => 'The sales invoice reference.',
						'optional'    => true,
					],
				],
			]
		);
	}
);
