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

// Ship the stylesheets as standalone files for wp_enqueue_style().
copyFileSync( resolve( root, 'src/admin/styles/admin.css' ), resolve( root, 'assets/build/admin.css' ) );
copyFileSync( resolve( root, 'src/frontend/styles/frontend.css' ), resolve( root, 'assets/build/frontend.css' ) );
