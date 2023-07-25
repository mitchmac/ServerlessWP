<script>
	import {afterUpdate, setContext} from "svelte";
	import {location, push} from "svelte-spa-router";
	import {
		current_settings,
		settingsLocked,
		needs_access_keys
	} from "../js/stores";
	import Page from "./Page.svelte";
	import Notifications from "./Notifications.svelte";
	import SubNav from "./SubNav.svelte";
	import SubPages from "./SubPages.svelte";
	import {pages} from "../js/routes";

	export let name = "storage";
	export let params = {}; // Required for regex routes.
	const _params = params; // Stops compiler warning about unused params export;

	// During initial setup some storage sub pages behave differently.
	// Not having a bucket defined is akin to initial setup, but changing provider in sub page may also flip the switch.
	if ( $current_settings.bucket ) {
		setContext( "initialSetup", false );
	} else {
		setContext( "initialSetup", true );
	}

	// Let all child components know if settings are currently locked.
	setContext( "settingsLocked", settingsLocked );

	const prefix = "/storage";

	let items = pages.withPrefix( prefix );
	let routes = pages.routes( prefix );

	afterUpdate( () => {
		items = pages.withPrefix( prefix );
		routes = pages.routes( prefix );

		// Ensure only Storage Provider subpage can be visited if credentials not set.
		if ( $needs_access_keys && $location.startsWith( "/storage/" ) && $location !== "/storage/provider" ) {
			push( "/storage/provider" );
		}
	} );
</script>

<Page {name} subpage on:routeEvent>
	<Notifications tab="media" tabParent="media"/>

	<SubNav {name} {items} progress/>

	<SubPages {name} {prefix} {routes} on:routeEvent/>
</Page>
