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
		 * Orbis Products.
		 * 
		 * @link https://github.com/pronamic/wp-orbis-subscriptions/commit/5f0606c6f4ac01dea3bbdd0e25f058eaaec9a82d
		 * @link https://github.com/pronamic/wp-pronamic-moneybird
		 */
		if ( post_type_exists( 'orbis_product' ) ) {
			add_post_type_support( 'orbis_product', 'pronamic_moneybird_ledger_account' );
			add_post_type_support( 'orbis_product', 'pronamic_moneybird_product' );
			add_post_type_support( 'orbis_product', 'pronamic_moneybird_project' );
			add_post_type_support( 'orbis_product', 'pronamic_moneybird_sales_invoice' );
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

add_action(
	'orbis_after_side_content',
	function () {
		if ( ! is_singular() ) {
			return;
		}

		if ( ! post_type_supports( get_post_field( 'post_type' ), 'pronamic_moneybird_product' ) ) {
			return;
		}

		include __DIR__ . '/templates/product-card.php';
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
				$wpdb->posts AS project_post
						ON project.post_id = project_post.ID
					INNER JOIN
				$wpdb->users AS manager
						ON project_post.post_author = manager.ID
					INNER JOIN
				$wpdb->orbis_companies AS principal
						ON project.principal_id = principal.id
					LEFT JOIN
				(
					SELECT
						invoice_line.project_id,
						SUM( invoice_line.seconds ) AS project_billed_time,
						SUM( invoice_line.amount ) AS project_billed_amount,
						GROUP_CONCAT( DISTINCT invoice.invoice_number ) AS project_invoice_numbers
					FROM
						$wpdb->orbis_invoices_lines AS invoice_line
							INNER JOIN
						$wpdb->orbis_invoices AS invoice
								ON invoice_line.invoice_id = invoice.id
					GROUP BY
						invoice_line.project_id
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
		$item = $wpdb->get_row( $query );

		if ( null === $item ) {
			return;
		}

		/**
		 * Invoicing moment.
		 * 
		 * @link https://www.credit-care.com/blogs/wanneer-factureren/
		 */
		$billing_strategy = 'progress';

		if ( str_contains( $item->project_name, 'Online marketing' ) ) {
			$billing_strategy = 'afterwards';
		}

		if ( str_contains( $item->project_name, 'Scrum' ) ) {
			$billing_strategy = 'afterwards';
		}

		if ( str_contains( $item->project_name, 'Strippenkaart' ) ) {
			$billing_strategy = 'in_advance';
		}

		$project_references = [];

		$project_references[] = $project_reference = '#project_' . $item->project_id;

		$project_billable_time = $item->project_billable_time;
		$project_billed_time   = $item->project_billed_time;
		$project_invoice_time  = $project_billable_time;

		if ( 'progress' === $billing_strategy ) {
			$project_invoice_time = min( $item->project_timesheet_time, $item->project_billable_time ) - $project_billed_time;
		}

		$references = [];

		$references[] = \implode( ', ', $project_references );

		if ( $project_invoice_time < $project_billable_time ) {
			$references[] = 'Deelfactuur';
		}

		$references[] = $item->project_name;

		if ( $project_invoice_time < $project_billable_time ) {
			$references[] = \sprintf(
				'%s / %s uren',
				orbis_time( min( $item->project_timesheet_time, $item->project_billable_time ) ),
				orbis_time( $item->project_billable_time )
			);
		}

		$references[] = \get_post_meta( $item->project_post_id, '_orbis_invoice_reference', true );

		if ( null !== $sales_invoice->reference && '' !== $sales_invoice->reference ) {
			$references[] = $sales_invoice->reference;
		}

		$sales_invoice->reference = implode( ' · ', \array_filter( $references ) );

		$detail = new \Pronamic\Moneybird\SalesInvoiceDetail();

		$date_start = DateTimeImmutable::createFromFormat( 'Y-m-d', \get_post_meta( $item->project_post_id, '_orbis_project_start_date', true ) );
		$date_end   = DateTimeImmutable::createFromFormat( 'Y-m-d', \get_post_meta( $item->project_post_id, '_orbis_project_end_date', true ) );

		$description_line = \get_post_meta( $item->project_post_id, '_orbis_invoice_line_description', true );

		$hourly_rate = \get_post_meta( $item->project_post_id, '_orbis_hourly_rate', true );

		$description_parts = [
			$item->project_name,
			$description_line,
			$project_reference,
		];

		$detail->description = \implode( ' · ', \array_unique( \array_filter( $description_parts ) ) );
		$detail->amount     = \orbis_time( $project_invoice_time );
		$detail->price      = $hourly_rate;

		if ( false !== $date_start && false !== $date_end ) {
			$detail->period = $date_start->format( 'Ymd' ) . '..' . $date_end->format( 'Ymd' );
		}

		if ( false !== \strpos( $item->project_name, 'Online marketing' ) ) {
			$detail->product_id = '411373237913519355';
		}

		$sales_invoice->details_attributes[] = $detail;

		/**
		 * Previous project invoices.
		 * 
		 * @link https://github.com/pronamic/orbis.pronamic.nl/issues/38
		 */
		$previous_project_invoices = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT
					invoice.id,
					invoice.created_at,
					invoice.invoice_number,
					SUM( invoice_line.amount ) AS amount,
					SUM( invoice_line.seconds ) AS seconds
				FROM
					$wpdb->orbis_invoices AS invoice
						LEFT JOIN
					$wpdb->orbis_invoices_lines AS invoice_line
							ON invoice_line.invoice_id = invoice.id
				WHERE
					invoice_line.project_id = %d
				GROUP BY
					invoice.id
				;
				",
				$project_id
			)
		);

		if ( count( $previous_project_invoices ) > 0 ) {
			$detail = new \Pronamic\Moneybird\SalesInvoiceDetail();

			$detail_lines = [];

			$detail_lines[] = '*' . 'Eerdere projectfacturen:' . '*';

			foreach ( $previous_project_invoices as $previous_project_invoice ) {
				$date = new DateTimeImmutable( $previous_project_invoice->created_at );

				$parts = [
					$date->format( 'd-m-Y' ),
					'Factuur ' . $previous_project_invoice->invoice_number,
					orbis_time( $previous_project_invoice->seconds ),
					'€ ' . number_format_i18n( $previous_project_invoice->amount, 2 ),
				];

				$detail_lines[] = '• ' . \implode( ' · ', $parts );
			}

			$detail->description = \implode( "\r\n", $detail_lines );

			$sales_invoice->details_attributes[] = $detail;
		}
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
						$wpdb->orbis_products AS product
								ON subscription.product_id = product.id
					WHERE
						subscription.id IN ( %s )
					",
					\implode( ', ', \array_fill( 0, count( $subscription_ids ), '%d' ) ),
				),
				$subscription_ids
			)
		);

		$references = [];

		foreach ( $subscriptions as $subscription ) {
			$references[] = \get_post_meta( $subscription->post_id, '_orbis_invoice_reference', true );

			$details = orbis_moneybird_subscription_get_sales_invoice_details( $subscription );

			foreach ( $details as $detail ) {
				$sales_invoice->details_attributes[] = $detail;
			}
		}

		if ( null !== $sales_invoice->reference && '' !== $sales_invoice->reference ) {
			$references[] = $sales_invoice->reference;
		}

		$sales_invoice->reference = implode( ' · ', \array_unique( \array_filter( $references ) ) );
	}
);

function orbis_moneybird_subscription_get_sales_invoice_details( $subscription ) {
	$subscription_reference = '#subscription_' . $subscription->id;

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

	/**
	 * Description.
	 */
	$description_line = get_post_meta( $subscription->post_id, '_orbis_invoice_line_description', true );

	$subscription_name = ( '' === $description_line ) ? $subscription->name : $description_line;

	/**
	 * Product sales invoice template.
	 */
	$json = \get_post_meta( $subscription->product_post_id, '_pronamic_moneybird_sales_invoice', true );

	$data = \json_decode( $json );

	if ( \is_object( $data ) && \property_exists( $data, 'details_attributes' ) && \is_array( $data->details_attributes ) ) {
		$detail_properties = [
			'amount',
			'description',
			'price',
			'product_id',
			'project_id',
		];

		$details = [];

		foreach ( $data->details_attributes as $detail_data ) {
			$detail = new \Pronamic\Moneybird\SalesInvoiceDetail();

			foreach ( $detail_properties as $property ) {
				if ( \property_exists( $detail_data, $property ) ) {
					$detail->$property = $detail_data->$property;
				}
			}

			$detail->period = $date_start->format( 'Ymd' ) . '..' . $date_end->format( 'Ymd' );

			$description = $detail->description;

			if ( '' === $description ) {
				$description = \sprintf(
					'%s · %s',
					$subscription->product_name,
					$subscription_name
				);
			}

			$description_parts = [
				\strtr(
					$description,
					[
						'{subscription_name}' => $subscription_name,
					]
				),
				$subscription_reference
			];

			$detail->description = \implode( ' · ', $description_parts );

			$details[] = $detail;
		}

		return $details;
	}

	$detail = new \Pronamic\Moneybird\SalesInvoiceDetail();

	$description_line = get_post_meta( $subscription->post_id, '_orbis_invoice_line_description', true );

	$description_parts = [
		$subscription->product_name,
		( '' === $description_line ) ? $subscription->name : $description_line,
		$subscription_reference
	];

	$description = \implode( ' · ', $description_parts );

	$detail->description = $description;
	$detail->amount      = '1';
	$detail->price       = $subscription->price;
	$detail->product_id  = \get_post_meta( $subscription->product_post_id, '_pronamic_moneybird_product_id', true );
	$detail->project_id  = \get_post_meta( $subscription->product_post_id, '_pronamic_moneybird_project_id', true );
	$detail->period      = $date_start->format( 'Ymd' ) . '..' . $date_end->format( 'Ymd' );

	return [
		$detail,
	];
}

add_action(
	'pronamic_moneybird_sales_invoice_created',
	function ( $sales_invoice ) {
		global $wpdb;

		$orbis_invoice_id = orbis_moneybird_insert_sales_invoice( $sales_invoice );

		$ids = [];

		foreach ( $sales_invoice->details as $detail ) {
			$result = \preg_match_all(
				'/#project_(?P<project_id>[0-9]+)/',
				$detail->description,
				$matches
			);

			if ( false === $result ) {
				continue;
			}

			$project_ids = \array_key_exists( 'project_id', $matches ) ? $matches['project_id'] : [];

			$period = ( null === $detail->period ) ? null : \Pronamic\Moneybird\Period::from_string( $detail->period );

			foreach ( $project_ids as $project_id ) {
				$ids[] = $project_id;

				$line_data = [
					'host'              => 'moneybird.com',
					'id'                => $sales_invoice->id,
					'administration_id' => $sales_invoice->administration_id,
					'contact_id'        => $sales_invoice->contact_id,
					'draft_id'          => $sales_invoice->draft_id,
					'detail_id'         => $detail->id,
				];

				$result = $wpdb->insert(
					$wpdb->orbis_invoices_lines,
					[
						'invoice_id'      => $orbis_invoice_id,
						'created_at'      => \gmdate( 'Y-m-d H:i:s' ),
						'line_number'     => $detail->id,
						'line_data'       => \wp_json_encode( $line_data ),
						'project_id'      => $project_id,
						'amount'          => $detail->total_price_excl_tax_with_discount_base,
						'seconds'         => $detail->amount_decimal * HOUR_IN_SECONDS,
						'start_date'      => ( null === $period ) ? null : $period->start_date->format( 'Y-m-d' ),
						'end_date'        => ( null === $period ) ? null : $period->end_date->format( 'Y-m-d' ),
					],
					[
						'invoice_id'      => '%d',
						'created_at'      => '%s',
						'line_number'     => '%s',
						'line_data'       => '%s',
						'project_id'      => '%d',
						'amount'          => '%f',
						'seconds'         => '%d',
						'start_date'      => '%s',
						'end_date'        => '%s',
					]
				);

				if ( false === $result ) {
					throw new \Exception( 'An error occurred while inserting the Moneybird sales invoice detail into the Orbis database: ' . $wpdb->last_error );
				}
			}
		}

		$wpdb->query(
			$wpdb->prepare(
				sprintf(
					"
					UPDATE
						$wpdb->orbis_projects AS project
							INNER JOIN
						(
							SELECT
								project.id AS project_id,
								invoice.id AS invoice_id,
								invoice.created_at AS invoice_created_at,
								invoice_line.start_date,
								invoice_line.end_date
							FROM
								$wpdb->orbis_projects AS project
									INNER JOIN
								$wpdb->orbis_invoices_lines AS invoice_line
										ON invoice_line.project_id = project.id
									INNER JOIN
								$wpdb->orbis_invoices AS invoice
										ON invoice.id = invoice_line.invoice_id
									INNER JOIN
								(
									SELECT
										invoice_line.project_id,
										MAX( invoice.created_at ) AS created_at
									FROM
										$wpdb->orbis_invoices_lines AS invoice_line
											INNER JOIN
										$wpdb->orbis_invoices AS invoice
												ON invoice.id = invoice_line.invoice_id
									GROUP BY
										invoice_line.project_id
								) AS last_invoice
										ON (
											last_invoice.project_id = invoice_line.project_id
												AND
											last_invoice.created_at = invoice.created_at
										)

						) AS project_invoice_data
							ON project.id = project_invoice_data.project_id
					SET
						project.billed_to = project_invoice_data.end_date
					WHERE
						project.id IN ( %s )
					;
					",
					\implode( ',', \array_fill( 0, \count( $ids ), '%d' ) )
				),
				$ids
			)
		);
	}
);

function orbis_moneybird_insert_sales_invoice( $sales_invoice ) {
	global $wpdb;

	$orbis_invoice = $wpdb->get_row(
		$wpdb->prepare(
			"
			SELECT
				invoice.*
			FROM
				$wpdb->orbis_invoices AS invoice
			WHERE
				invoice.invoice_number = %s
			LIMIT
				1
			;
			",
			$sales_invoice->id
		)
	);

	if ( null !== $orbis_invoice ) {
		return $orbis_invoice->id;
	}

	$invoice_data = [
		'host'              => 'moneybird.com',
		'id'                => $sales_invoice->id,
		'administration_id' => $sales_invoice->administration_id,
		'contact_id'        => $sales_invoice->contact_id,
		'draft_id'          => $sales_invoice->draft_id,
	];

	$result = $wpdb->insert(
		$wpdb->orbis_invoices,
		[
			'created_at'     => \gmdate( 'Y-m-d H:i:s' ),
			'invoice_number' => $sales_invoice->id,
			'invoice_data'   => \wp_json_encode( $invoice_data ),
			'user_id'        => \get_current_user_id(),
		],
		[
			'%s',
			'%s',
			'%s',
			'%d',
		]
	);

	if ( false === $result ) {
		throw new \Exception( 'An error occurred while inserting the Moneybird sales invoice into the Orbis database: ' . $wpdb->last_error );
	}

	return $wpdb->insert_id;
}

add_action(
	'pronamic_moneybird_sales_invoice_created',
	function ( $sales_invoice ) {
		global $wpdb;

		$orbis_invoice_id = orbis_moneybird_insert_sales_invoice( $sales_invoice );

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

				$line_data = [
					'host'              => 'moneybird.com',
					'id'                => $sales_invoice->id,
					'administration_id' => $sales_invoice->administration_id,
					'contact_id'        => $sales_invoice->contact_id,
					'draft_id'          => $sales_invoice->draft_id,
					'detail_id'         => $detail->id,
				];

				$result = $wpdb->insert(
					$wpdb->orbis_invoices_lines,
					[
						'invoice_id'      => $orbis_invoice_id,
						'created_at'      => \gmdate( 'Y-m-d H:i:s' ),
						'line_number'     => $detail->id,
						'line_data'       => \wp_json_encode( $line_data ),
						'subscription_id' => $subscription_id,
						'amount'          => $detail->total_price_excl_tax_with_discount_base,
						'start_date'      => ( null === $period ) ? null : $period->start_date->format( 'Y-m-d' ),
						'end_date'        => ( null === $period ) ? null : DateTimeImmutable::createFromInterface( $period->end_date )->modify( '+1 day' )->format( 'Y-m-d' ),
					],
					[
						'invoice_id'      => '%d',
						'created_at'      => '%s',
						'line_number'     => '%s',
						'line_data'       => '%s',
						'subscription_id' => '%d',
						'amount'          => '%f',
						'start_date'      => '%s',
						'end_date'        => '%s',
					]
				);

				if ( false === $result ) {
					throw new \Exception( 'An error occurred while inserting the Moneybird sales invoice detail into the Orbis database: ' . $wpdb->last_error );
				}
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
								invoice.id AS invoice_id,
								invoice.created_at AS invoice_created_at,
								invoice_line.start_date,
								invoice_line.end_date
							FROM
								$wpdb->orbis_subscriptions AS subscription
									INNER JOIN
								$wpdb->orbis_invoices_lines AS invoice_line
										ON invoice_line.subscription_id = subscription.id
									INNER JOIN
								$wpdb->orbis_invoices AS invoice
										ON invoice.id = invoice_line.invoice_id
									INNER JOIN
								(
									SELECT
										invoice_line.subscription_id,
										MAX( invoice.created_at ) AS created_at
									FROM
										$wpdb->orbis_invoices_lines AS invoice_line
											INNER JOIN
										$wpdb->orbis_invoices AS invoice
												ON invoice.id = invoice_line.invoice_id
									GROUP BY
										invoice_line.subscription_id
								) AS last_invoice
										ON (
											last_invoice.subscription_id = invoice_line.subscription_id
												AND
											last_invoice.created_at = invoice.created_at
										)

						) AS subscription_invoice_data
							ON subscription.id = subscription_invoice_data.subscription_id
					SET
						subscription.billed_to = subscription_invoice_data.end_date
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
