<?php
/**
 * Bundle shortcode.
 *
 * @package BundleCraft
 */

namespace BundleCraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the [bundlecraft_bundle] shortcode.
 */
class Shortcode {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_shortcode( 'bundlecraft_bundle', [ $this, 'render' ] );
	}

	/**
	 * Renders a bundle widget.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			[ 'id' => 0 ],
			$atts,
			'bundlecraft_bundle'
		);

		$bundle = Bundles::get( absint( $atts['id'] ) );

		if ( ! $bundle ) {
			return '<p class="bundlecraft-error">' . esc_html__( 'Bundle not found.', 'bundlecraft-for-woocommerce' ) . '</p>';
		}

		if ( ! $bundle['enabled'] ) {
			return '';
		}

		return Frontend::render_bundle( $bundle );
	}
}
