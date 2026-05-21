<script>
	import Router from "svelte-spa-router";
	import {routes} from "../js/routes";
	import Nav from "./Nav.svelte";

	/**
	 * @typedef {Object} Props
	 * @property {any} [nav] - These components can be overridden.
	 * @property {import("svelte").Snippet} [children]
	 * @property {string} [class]
	 */

	/** @type {Props} */
	let { nav = Nav, children, class: classes = "" } = $props();

	const NavComponent = $derived( nav );
</script>

<NavComponent/>

<div class="wpome-wrapper {classes}">
	<Router routes={$routes}/>
	{#if children}
		{@render children()}
	{:else}
		<!-- EXTRA CONTENT GOES HERE -->
	{/if}
</div>
