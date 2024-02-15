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

/**
 * Bootstrap.
 */
\add_action(
	'plugins_loaded',
	function () {
		\load_plugin_textdomain( 'orbis-moneybird', false, \dirname( \plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

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

		include __DIR__ . '/templates/contact-card.php';
	}
);


\add_action(
	'pronamic_moneybird_new_sales_invoice',
	function ( $sales_invoice ) {
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! \array_key_exists( 'orbis_project_id', $_GET ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$project_id = \sanitize_text_field( \wp_unslash( $_GET['orbis_project_id'] ) );

		$where = '1 = 1';

		$where .= $wpdb->prepare( ' AND project.id = %d', $project_id );

		$query = "
			SELECT
				project.id AS project_id,
				project.name AS project_name,
				project.billable_amount AS project_billable_amount,
				project.number_seconds AS project_billable_time,
				project.invoice_number AS project_invoice_number,
				project.post_id AS project_post_id,
				project.start_date AS project_start_date,
				manager.ID AS project_manager_id,
				manager.display_name AS project_manager_name,
				principal.id AS principal_id,
				principal.name AS principal_name,
				principal.post_id AS principal_post_id,
				project_invoice_totals.project_billed_time,
				project_invoice_totals.project_billed_amount,
				project_invoice_totals.project_invoice_numbers,
				project_timesheet_totals.project_timesheet_time
			FROM
				$wpdb->orbis_projects AS project
					INNER JOIN
				wp_posts AS project_post
						ON project.post_id = project_post.ID
					INNER JOIN
				wp_users AS manager
						ON project_post.post_author = manager.ID
					INNER JOIN
				$wpdb->orbis_companies AS principal
						ON project.principal_id = principal.id
					LEFT JOIN
				(
					SELECT
						project_invoice.project_id,
						SUM( project_invoice.seconds ) AS project_billed_time,
						SUM( project_invoice.amount ) AS project_billed_amount,
						GROUP_CONCAT( DISTINCT project_invoice.invoice_number ) AS project_invoice_numbers
					FROM
						$wpdb->orbis_projects_invoices AS project_invoice
					GROUP BY
						project_invoice.project_id
				) AS project_invoice_totals ON project_invoice_totals.project_id = project.id
					LEFT JOIN
				(
					SELECT
						project_timesheet.project_id,
						SUM( project_timesheet.number_seconds ) AS project_timesheet_time
					FROM
						$wpdb->orbis_timesheets AS project_timesheet
					GROUP BY
						project_timesheet.project_id
				) AS project_timesheet_totals ON project_timesheet_totals.project_id = project.id
			WHERE
				$where
			GROUP BY
				project.id
			ORDER BY
				principal.name
			;
		";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$project = $wpdb->get_row( $query );

		if ( null === $project ) {
			return;
		}

		$sales_invoice->contact_id = \get_post_meta( $project->principal_post_id, '_pronamic_moneybird_contact_id', true );

		echo '<pre>';
		\var_dump( $project );
		echo '</pre>';
		exit;
	}
);

\add_action(
	'pronamic_moneybird_new_sales_invoice',
	function ( $sales_invoice ) {
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! \array_key_exists( 'orbis_company_id', $_GET ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$company_id = \sanitize_text_field( \wp_unslash( $_GET['orbis_company_id'] ) );

		$company = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $wpdb->orbis_companies WHERE id = %d;", $company_id ) );

		if ( null === $company ) {
			return;
		}

		$sales_invoice->contact_id = get_post_meta( $company->post_id, '_pronamic_moneybird_contact_id', true );
		$sales_invoice->reference  = get_post_meta( $company->post_id, '_orbis_invoice_reference', true );
	}
);

\add_action(
	'pronamic_moneybird_new_sales_invoice',
	function ( $sales_invoice ) {
		global $wpdb;

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! \array_key_exists( 'orbis_subscription_ids', $_GET ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$subscription_ids = wp_parse_id_list( \sanitize_text_field( \wp_unslash( $_GET['orbis_subscription_ids'] ) ) );

		$subscriptions = $wpdb->get_results(
			$wpdb->prepare(
				\sprintf(
					"
					SELECT
						subscription.id,
						subscription.post_id,
						subscription.name,
						DATE(
							COALESCE(
								subscription.billed_to,
								subscription.activation_date,
								NOW()
							)
						) AS start_date,
						product.name AS product_name,
						product.post_id AS product_post_id,
						product.interval AS product_interval,
						product.price
					FROM
						$wpdb->orbis_subscriptions AS subscription
							INNER JOIN
						$wpdb->orbis_subscription_products AS product
								ON subscription.type_id = product.id
					WHERE
						subscription.id IN ( %s )
					",
					\implode( ', ', \array_fill( 0, count( $subscription_ids ), '%d' ) ),
				),
				$subscription_ids
			)
		);

		$subscription_references = [];

		foreach ( $subscriptions as $subscription ) {
			$subscription_references[] = $subscription_reference = '#subscription_' . $subscription->id;

			$detail = new \Pronamic\Moneybird\SalesInvoiceDetail();

			$date_start = DateTimeImmutable::createFromFormat( 'Y-m-d', $subscription->start_date )->setTime( 0, 0 );

			switch ( $subscription->product_interval ) {
				case 'M':
					$date_end = $date_start->modify( '+1 month' );
					break;
				case 'Y':
					$date_end = $date_start->modify( '+1 year' );
					break;
				default:
					throw new Exception( 'Unsupported product interval: ' . $subscription->product_interval );
			}

			/**
			 * In Orbis a subscription annual period is from, for example, 2005-01-01 to 2006-01-01 (exclusive).
			 * In Moneybrid and accounting this actually runs from 2005-01-01 to 2005-12-31 (inclusive).
			 * That's why we're moving the end date back one day.
			 * 
			 * @link https://taaladvies.net/tot-of-tot-en-met/
			 */
			$date_end = $date_end->modify( '-1 day' );

			$description_line = get_post_meta( $subscription->post_id, '_orbis_invoice_line_description', true );

			$description_parts = [
				$subscription->product_name,
				( '' === $description_line ) ? $subscription->name : $description_line,
				$subscription_reference
			];

			$detail->description = \implode( ' · ', $description_parts );
			$detail->amount      = '1';
			$detail->price       = $subscription->price;
			$detail->product_id  = \get_post_meta( $subscription->product_post_id, '_pronamic_moneybird_product_id', true );
			$detail->period      = $date_start->format( 'Ymd' ) . '..' . $date_end->format( 'Ymd' );

			$sales_invoice->details_attributes[] = $detail;
		}

		$references = [];

		$references[] = \implode( ', ', $subscription_references );

		if ( null !== $sales_invoice->reference && '' !== $sales_invoice->reference ) {
			$references[] = $sales_invoice->reference;
		}

		$sales_invoice->reference = implode( ' · ', \array_filter( $references ) );
	}
);

add_action(
	'pronamic_moneybird_sales_invoice_created',
	function ( $sales_invoice ) {
		global $wpdb;

		$ids = [];

		foreach ( $sales_invoice->details as $detail ) {
			$result = \preg_match_all(
				'/#subscription_(?P<subscription_id>[0-9]+)/',
				$detail->description,
				$matches
			);

			if ( false === $result ) {
				continue;
			}

			$subscription_ids = \array_key_exists( 'subscription_id', $matches ) ? $matches['subscription_id'] : [];

			$period = ( null === $detail->period ) ? null : \Pronamic\Moneybird\Period::from_string( $detail->period );

			foreach ( $subscription_ids as $subscription_id ) {
				$ids[] = $subscription_id;

				$invoice_data = [
					'host'              => 'moneybird.com',
					'id'                => $sales_invoice->id,
					'administration_id' => $sales_invoice->administration_id,
					'contact_id'        => $sales_invoice->contact_id,
					'draft_id'          => $sales_invoice->draft_id,
					'detail_id'         => $detail->id,
				];

				$wpdb->insert(
					$wpdb->orbis_subscriptions_invoices,
					[
						'created_at'      => \gmdate( 'Y-m-d H:i:s' ),
						'subscription_id' => $subscription_id,
						'invoice_number'  => $sales_invoice->id,
						'invoice_data'    => \wp_json_encode( $invoice_data ),
						'start_date'      => ( null === $period ) ? null : $period->start_date->format( 'Y-m-d' ),
						'end_date'        => ( null === $period ) ? null : DateTimeImmutable::createFromInterface( $period->end_date )->modify( '+1 day' )->format( 'Y-m-d' ),
						'user_id'         => get_current_user_id(),
					],
					[
						'%s',
						'%d',
						'%s',
						'%s',
						'%s',
						'%s',
						'%d',
					]
				);
			}
		}

		$wpdb->query(
			$wpdb->prepare(
				sprintf(
					"
					UPDATE
						$wpdb->orbis_subscriptions AS subscription
							INNER JOIN
						(
							SELECT
								subscription.id AS subscription_id,
								subscription_invoice.id AS invoice_id,
								subscription_invoice.created_at AS invoice_created_at,
								subscription_invoice.start_date,
								subscription_invoice.end_date
							FROM
								$wpdb->orbis_subscriptions AS subscription
									INNER JOIN
								$wpdb->orbis_subscriptions_invoices AS subscription_invoice
										ON subscription_invoice.subscription_id = subscription.id
									INNER JOIN
								(
									SELECT
										subscription_invoice.subscription_id,
										MAX( subscription_invoice.created_at ) AS created_at
									FROM
										$wpdb->orbis_subscriptions_invoices AS subscription_invoice
									GROUP BY
										subscription_invoice.subscription_id
								) AS last_invoice
										ON (
											last_invoice.subscription_id = subscription_invoice.subscription_id
												AND
											last_invoice.created_at = subscription_invoice.created_at
										)

						) AS subscription_invoice_data
							ON subscription.id = subscription_invoice_data.subscription_id
					SET
						subscription.billed_to = subscription_invoice_data.end_date,
						subscription.expiration_date = subscription_invoice_data.end_date
					WHERE
						subscription.id IN ( %s )
					;
					",
					\implode( ',', \array_fill( 0, \count( $ids ), '%d' ) )
				),
				$ids
			)
		);
	}
);

add_filter(
	'orbis_invoice_url',
	function ( $url, $data ) {
		if ( '' === $data ) {
			return $url;
		}

		$info = json_decode( $data );

		if ( ! is_object( $info ) ) {
			return $url;
		}

		if ( ! property_exists( $info, 'host' ) ) {
			return $url;
		}

		if ( ! property_exists( $info, 'administration_id' ) ) {
			return $url;
		}

		if ( 'moneybird.com' !== $info->host ) {
			return $url;
		}

		if ( property_exists( $info, 'id' ) ) {
			$url = sprintf(
				'https://moneybird.com/%s/sales_invoices/%s',
				$info->administration_id,
				$info->id
			);
		}

		if ( property_exists( $info, 'invoice_id' ) ) {
			$url = add_query_arg(
				[
					'search_query' => $info->invoice_id,
					'type_filter'  => 'sales_invoices',
				],
				sprintf(
					'https://moneybird.com/%s/search',
					$info->administration_id
				)
			);
		}

		return $url;
	},
	10,
	2
);
