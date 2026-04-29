<script>
	import {config, urls} from "../js/stores";
	/**
	 * @typedef {Object} Props
	 * @property {import("svelte").Snippet} [children]
	 */

	/** @type {Props} */
	let { children } = $props();

	let header_img_url = $derived( $urls.assets + "img/brand/ome-medallion.svg" );
</script>

<div class="header">
	<div class="header-wrapper">
		<img class="medallion" src={header_img_url} alt="{$config.title} logo">
		<h1>{$config.title}</h1>
		<div class="licence">
			{@render children?.()}
		</div>
	</div>
</div>
