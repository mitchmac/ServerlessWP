<script>
	import Router from "svelte-spa-router";
	import {push} from "svelte-spa-router";
	import {pages, routes} from "../js/routes";
	import Nav from "./Nav.svelte";

	// These components can be overridden.
	export let nav = Nav;

	const classes = $$props.class ? $$props.class : "";

	/**
	 * Handles events published by the router.
	 *
	 * This handler gives pages a chance to put their hand up and
	 * provide a new route to be navigated to in response
	 * to some event.
	 * e.g. settings saved resulting in a question being asked.
	 *
	 * @param {Object} event
	 */
	function handleRouteEvent( event ) {
		const route = pages.handleRouteEvent( event.detail );

		if ( route ) {
			push( route );
		}
	}
</script>

<svelte:component this={nav}/>

<div class="wpome-wrapper {classes}">
	<Router routes={$routes} on:routeEvent={handleRouteEvent}/>
	<slot>
		<!-- EXTRA CONTENT GOES HERE -->
	</slot>
</div>
