/**
 * Builds the two Vue bundles as standalone IIFE files.
 *
 * Each entry (admin app, storefront widget) gets its own Vite build so the
 * output contains no shared chunks and no ES module imports — WordPress
 * enqueues plain <script> tags, so every file must be self-contained.
 */
import { copyFileSync, mkdirSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { build } from 'vite';
import vue from '@vitejs/plugin-vue';

const root = resolve( fileURLToPath( import.meta.url ), '..', '..' );

const shared = {
	plugins: [ vue() ],
	build: {
		outDir: resolve( root, 'assets/build' ),
		emptyOutDir: false,
		cssCodeSplit: true,
		rollupOptions: {
			output: {
				format: 'iife',
				entryFileNames: '[name].js',
				assetFileNames: '[name].[ext]',
			},
		},
	},
};

rmSync( resolve( root, 'assets/build' ), { recursive: true, force: true } );
mkdirSync( resolve( root, 'assets/build' ), { recursive: true } );

await build( {
	...shared,
	root,
	build: {
		...shared.build,
		rollupOptions: {
			...shared.build.rollupOptions,
			input: { admin: resolve( root, 'src/admin/main.js' ) },
		},
	},
} );

await build( {
	...shared,
	root,
	build: {
		...shared.build,
		rollupOptions: {
			...shared.build.rollupOptions,
			input: { frontend: resolve( root, 'src/frontend/main.js' ) },
		},
	},
} );

// The block editor entry uses WordPress's own bundled libraries: they are
// marked external and resolved from the wp.* globals at runtime, so the
// editor shares the block editor's single React instance.
const wpGlobals = {
	'@wordpress/blocks': 'wp.blocks',
	'@wordpress/element': 'wp.element',
	'@wordpress/components': 'wp.components',
	'@wordpress/i18n': 'wp.i18n',
	'@wordpress/block-editor': 'wp.blockEditor',
	'@wordpress/api-fetch': 'wp.apiFetch',
	'@wordpress/server-side-render': 'wp.serverSideRender',
	'@wordpress/data': 'wp.data',
};

await build( {
	...shared,
	root,
	build: {
		...shared.build,
		rollupOptions: {
			...shared.build.rollupOptions,
			input: { 'block-editor': resolve( root, 'src/blocks/editor.js' ) },
			external: [ ( id ) => id.startsWith( '@wordpress/' ) ],
			output: {
				...shared.build.rollupOptions.output,
				globals: wpGlobals,
			},
		},
	},
} );

// Ship the stylesheets as standalone files for wp_enqueue_style().
copyFileSync( resolve( root, 'src/admin/styles/admin.css' ), resolve( root, 'assets/build/admin.css' ) );
copyFileSync( resolve( root, 'src/frontend/styles/frontend.css' ), resolve( root, 'assets/build/frontend.css' ) );
