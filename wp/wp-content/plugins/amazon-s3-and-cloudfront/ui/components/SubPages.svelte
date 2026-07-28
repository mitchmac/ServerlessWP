<script>
	import Router from "svelte-spa-router";

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {string} [prefix]
	 * @property {any} [routes]
	 * @property {import("svelte").Snippet} [children]
	 * @property {function} [onRouteEvent]
	 */

	/** @type {Props} */
	let {
		name = "sub",
		prefix = "",
		routes = {},
		children,
		onRouteEvent
	} = $props();
</script>

{#if routes}
	<div class="{name}-page wrapper">
		<Router {routes} {prefix} {onRouteEvent}/>
		{#if children}
			{@render children()}
		{:else}
			<!-- EXTRA CONTENT GOES HERE -->
		{/if}
	</div>
{/if}
