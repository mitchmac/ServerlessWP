<script>
	import Router from "svelte-spa-router";

	export let name = "sub";
	export let prefix = "";
	export let routes = {};
</script>

{#if routes}
	<div class="{name}-page wrapper">
		<Router {routes} {prefix} on:routeEvent/>
		<slot>
			<!-- EXTRA CONTENT GOES HERE -->
		</slot>
	</div>
{/if}