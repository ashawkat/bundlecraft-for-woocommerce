<?php
/**
 * Bundle widget shell.
 *
 * The Vue storefront app mounts into this wrapper and hydrates from the
 * embedded JSON payload. Included from Frontend::render_bundle().
 *
 * @package BundleCraft
 *
 * @var array $payload Bundle + products payload.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bundlecraft_instance_id = 'bundlecraft-widget-' . (int) $payload['bundle']['id'] . '-' . wp_rand( 100, 999 );
?>
<div class="bundlecraft-bundle-wrapper" id="<?php echo esc_attr( $bundlecraft_instance_id ); ?>" data-bundlecraft-payload="<?php echo esc_attr( (string) wp_json_encode( $payload ) ); ?>">
	<div class="bundlecraft-widget-loading" aria-hidden="true">
		<div class="bundlecraft-skeleton bundlecraft-skeleton--title"></div>
		<div class="bundlecraft-skeleton-grid">
			<span class="bundlecraft-skeleton"></span>
			<span class="bundlecraft-skeleton"></span>
			<span class="bundlecraft-skeleton"></span>
		</div>
	</div>
	<noscript>
		<p><?php esc_html_e( 'This bundle builder requires JavaScript.', 'bundlecraft-for-woocommerce' ); ?></p>
	</noscript>
</div>
