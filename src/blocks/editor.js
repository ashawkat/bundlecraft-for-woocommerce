/**
 * BundleCraft Gutenberg block.
 *
 * Written against the WordPress editor's own libraries (wp.element,
 * wp.blocks, ...) which are provided as externals at build time, so the
 * editor shares the same React instance as the block editor.
 */

const { registerBlockType, createBlock } = wp.blocks;
const { createElement: h, useState, useEffect } = wp.element;
const { __ } = wp.i18n;
const { Placeholder, SelectControl, Spinner, Button } = wp.components;
const { InspectorControls, useBlockProps } = wp.blockEditor;
const apiFetch = wp.apiFetch;

const ServerSideRender = wp.serverSideRender && wp.serverSideRender.default
	? wp.serverSideRender.default
	: wp.serverSideRender;

function BundleIcon() {
	return h(
		'svg',
		{ viewBox: '0 0 24 24', width: 24, height: 24 },
		h( 'path', {
			fill: 'currentColor',
			d: 'M12 2l7 3.5v4L12 13 5 9.5v-4L12 2zm-7 9.7l7 3.5 7-3.5v4.8l-7 3.5-7-3.5v-4.8z',
		} )
	);
}

function BundlePicker( { value, bundles, onChange } ) {
	const options = [
		{ value: 0, label: __( 'Select a bundle…', 'bundlecraft-for-woocommerce' ) },
		...bundles.map( ( bundle ) => ( {
			value: bundle.id,
			label: `${ bundle.name } (ID ${ bundle.id })`,
		} ) ),
	];

	return h( SelectControl, {
		value: value || 0,
		options,
		label: __( 'Bundle', 'bundlecraft-for-woocommerce' ),
		onChange,
	} );
}

function BundlesTable( { onPick } ) {
	const [ bundles, setBundles ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		apiFetch( { path: '/bundlecraft/v1/bundles-list' } )
			.then( ( list ) => setBundles( list ) )
			.catch( ( err ) => {
				setError( err.message || __( 'Could not load bundles.', 'bundlecraft-for-woocommerce' ) );
				setBundles( [] );
			} );
	}, [] );

	if ( bundles === null ) {
		return h( Spinner );
	}

	if ( error ) {
		return h( 'p', { style: { color: '#d63638' } }, error );
	}

	if ( ! bundles.length ) {
		return h(
			'div',
			{},
			h( 'p', {}, __( 'You have not created any bundles yet.', 'bundlecraft-for-woocommerce' ) ),
			h(
				Button,
				{
					variant: 'primary',
					href: 'admin.php?page=bundlecraft',
					target: '_top',
				},
				__( 'Create your first bundle', 'bundlecraft-for-woocommerce' )
			)
		);
	}

	return h( BundlePicker, { value: 0, bundles, onChange: onPick } );
}

function EditBlock( props ) {
	const { attributes, setAttributes } = props;
	const { bundleId } = attributes;
	const blockProps = useBlockProps();

	function renderInspector() {
		return h(
			InspectorControls,
			{},
			h(
				'div',
				{ style: { padding: '16px' } },
				h( 'h3', { style: { margin: '0 0 12px' } }, __( 'BundleCraft', 'bundlecraft-for-woocommerce' ) ),
				h( 'p', { style: { margin: '0 0 8px', fontSize: '12px', color: '#757575' } },
					__( 'Manage bundles in Dashboard → BundleCraft.', 'bundlecraft-for-woocommerce' ) )
			)
		);
	}

	let body;

	if ( ! bundleId ) {
		body = h(
			Placeholder,
			{
				icon: h( BundleIcon ),
				label: __( 'BundleCraft Bundle', 'bundlecraft-for-woocommerce' ),
				instructions: __( 'Choose which bundle to display on this page.', 'bundlecraft-for-woocommerce' ),
			},
			h( BundlesTable, {
				onPick( id ) {
					if ( id ) {
						setAttributes( { bundleId: Number( id ) } );
					}
				},
			} )
		);
	} else if ( ! ServerSideRender ) {
		body = h( 'p', {}, __( 'Loading preview…', 'bundlecraft-for-woocommerce' ) );
	} else {
		body = h(
			'div',
			{},
			h( ServerSideRender, {
				block: 'bundlecraft/bundle',
				attributes: { bundleId },
			} ),
			h(
				'div',
				{ style: { marginTop: '8px', textAlign: 'right' } },
				h(
					Button,
					{ variant: 'tertiary', isDestructive: true, onClick: () => setAttributes( { bundleId: 0 } ) },
					__( 'Change bundle', 'bundlecraft-for-woocommerce' )
				)
			)
		);
	}

	return h(
		'div',
		{ ...blockProps },
		renderInspector(),
		body
	);
}

registerBlockType( 'bundlecraft/bundle', {
	apiVersion: 3,
	title: __( 'BundleCraft Bundle', 'bundlecraft-for-woocommerce' ),
	description: __(
		'Display a BundleCraft bundle builder widget with tiered quantity discounts.',
		'bundlecraft-for-woocommerce'
	),
	icon: BundleIcon,
	category: 'woocommerce',
	keywords: [ __( 'bundle' ), 'bundlecraft', 'woocommerce', __( 'discount' ) ],
	attributes: {
		bundleId: {
			type: 'integer',
			default: 0,
		},
	},
	supports: {
		html: false,
		customClassName: false,
	},
	transforms: {
		from: [
			{
				type: 'shortcode',
				tag: 'bundlecraft_bundle',
				attributes: {
					bundleId: {
						type: 'integer',
						shortcode: ( named, content ) => {
							const id = parseInt( ( named && named.id ) || content, 10 );
							return Number.isNaN( id ) ? 0 : id;
						},
					},
				},
				transform( attributes ) {
					return createBlock( 'bundlecraft/bundle', {
						bundleId: attributes.bundleId || 0,
					} );
				},
			},
		],
	},
	edit: EditBlock,
	save() {
		// Dynamic block; rendering happens server side.
		return null;
	},
} );
