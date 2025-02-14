import svelte from 'rollup-plugin-svelte';
import commonjs from '@rollup/plugin-commonjs';
import resolve from '@rollup/plugin-node-resolve';
import css from 'rollup-plugin-css-only';

// const production = !process.env.ROLLUP_WATCH;
const production = false; // Keep runtime checks for the moment.

export default [{
	input: 'ui/lite/Settings.svelte',
	output: {
		sourcemap: true,
		format: 'umd',
		name: 'AS3CF_Settings',
		file: 'ui/build/js/settings.js'
	},
	plugins: [
		svelte( {
			compilerOptions: {
				// enable run-time checks when not in production
				dev: !production
			}
		} ),
		// we'll extract any component CSS out into
		// a separate file - better for performance
		css( { output: 'settings.css' } ),

		// If you have external dependencies installed from
		// npm, you'll most likely need these plugins. In
		// some cases you'll need additional configuration -
		// consult the documentation for details:
		// https://github.com/rollup/plugins/tree/master/packages/commonjs
		resolve( {
			browser: true,
			dedupe: ['svelte']
		} ),
		commonjs()
	],
	watch: {
		clearScreen: false
	}
}, {
	input: 'ui/pro/Settings.svelte',
	output: {
		sourcemap: true,
		format: 'umd',
		name: 'AS3CFPro_Settings',
		file: 'ui/build/js/pro/settings.js'
	},
	plugins: [
		svelte( {
			compilerOptions: {
				// enable run-time checks when not in production
				dev: !production
			}
		} ),
		// we'll extract any component CSS out into
		// a separate file - better for performance
		css( { output: 'settings.css' } ),

		// If you have external dependencies installed from
		// npm, you'll most likely need these plugins. In
		// some cases you'll need additional configuration -
		// consult the documentation for details:
		// https://github.com/rollup/plugins/tree/master/packages/commonjs
		resolve( {
			browser: true,
			dedupe: ['svelte']
		} ),
		commonjs()
	],
	watch: {
		clearScreen: false
	}
}];
