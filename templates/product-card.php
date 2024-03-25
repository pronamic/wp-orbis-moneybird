<?php
/**
 * Product card
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2024 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Moneybird
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$authorization_id = get_option( 'pronamic_moneybird_authorization_post_id' );

$administration_id = '';

if ( '' !== $authorization_id ) {
	$administration_id = get_post_meta( (int) $authorization_id, '_pronamic_moneybird_administration_id', true );
}

$product_id = get_post_meta( get_post_field( 'ID' ), '_pronamic_moneybird_product_id', true );

?>
<div class="card mt-3">
	<div class="card-header"><?php esc_html_e( 'Moneybird', 'orbis-moneybird' ); ?></div>

	<div class="card-body">
		<dl>
			<dt><?php esc_html_e( 'Product ID', 'orbis-moneybird' ); ?></dt>
			<dd>
				<?php

				if ( '' === $product_id ) {
					printf(
						'<em>%s</em>',
						esc_html__( 'No Moneybird product ID set.', 'orbis-moneybird' )
					);
				}

				if ( '' !== $product_id && '' === $administration_id ) {
					printf(
						'<code>%s</code>',
						esc_html( $product_id )
					);
				}

				if ( '' !== $product_id && '' !== $administration_id ) {
					$url = \strtr(
						'https://moneybird.com/:administration_id/products/:product_id',
						[
							':administration_id' => $administration_id,
							':product_id'        => $product_id,
						]
					);

					printf(
						'<a href="%s"><code>%s</code></a>',
						esc_url( $url ),
						esc_html( $product_id )
					);
				}

				?>
			</dd>
		</dl>
	</div>
</div>
