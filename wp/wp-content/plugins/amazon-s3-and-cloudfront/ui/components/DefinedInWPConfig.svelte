<script>
	import {strings} from "../js/stores";

	/**
	 * @typedef {Object} Props
	 * @property {boolean} [defined]
	 */

	/** @type {Props} */
	let { defined = false } = $props();
</script>

{#if defined}
	<p class="wp-config">{$strings.defined_in_wp_config}</p>
{/if}