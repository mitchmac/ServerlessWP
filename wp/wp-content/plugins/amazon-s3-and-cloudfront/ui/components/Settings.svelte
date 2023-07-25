<script>
	import {onMount} from "svelte";
	import {config, notifications, settings, state} from "../js/stores";
	import Header from "./Header.svelte";

	// These components can be overridden.
	export let header = Header;
	export let footer = null;

	// We need a disassociated copy of the initial settings to work with.
	settings.set( { ...$config.settings } );

	// We might have some initial notifications to display too.
	if ( $config.notifications.length ) {
		for ( const notification of $config.notifications ) {
			notifications.add( notification );
		}
	}

	onMount( () => {
		// Periodically check the state.
		state.startPeriodicFetch();

		// Be a good citizen and clean up the timer when exiting our settings.
		return () => state.stopPeriodicFetch();
	} );
</script>

{#if header}
	<svelte:component this={header}/>
{/if}
<slot>
	<!-- CONTENT GOES HERE -->
</slot>
{#if footer}
	<svelte:component this={footer}/>
{/if}