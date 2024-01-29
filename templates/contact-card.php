<?php
/**
 * Contact card
 *
 * @author    Pronamic <info@pronamic.eu>
 * @copyright 2005-2024 Pronamic
 * @license   GPL-2.0-or-later
 * @package   Pronamic\Orbis\Moneybird
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$administration_id = '';
$contact_id        = get_post_meta( get_post_field( 'ID' ), '_pronamic_moneybird_contact_id', true );

?>
<div class="card mt-3">
	<div class="card-header"><?php esc_html_e( 'Moneybrid', 'orbis-moneybrid' ); ?></div>

	<div class="card-body">
		<dl>
			<dt><?php esc_html_e( 'Contact ID', 'orbis-moneybrid' ); ?></dt>
			<dd>
				<?php

				$url = \strtr(
					'https://moneybird.com/:administration_id/contacts/:contact_id',
					[
						':administration_id' => $administration_id,
						':contact_id'        => $contact_id,
					]
				);

				printf(
					'<a href="%s"><code>%s</code></a>',
					esc_url( $url ),
					esc_html( $contact_id )
				);

				?>
			</dd>
		</dl>
	</div>
</div>
