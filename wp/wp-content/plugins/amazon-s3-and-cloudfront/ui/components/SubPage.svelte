<script>
	import active from "svelte-spa-router/active";

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {string} [route]
	 * @property {import("svelte").Snippet} [children]
	 */

	/** @type {Props} */
	let { name = "", route = "/", children } = $props();
</script>

<div class="{name}" use:active={route}>
	{@render children?.()}
</div>
