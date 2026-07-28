<script>
	import {onMount} from "svelte";
	import {config, notifications, settings, appState} from "../js/stores";
	import Header from "./Header.svelte";

	/**
	 * @typedef {Object} Props
	 * @property {any} [header] - These components can be overridden.
	 * @property {any} [footer]
	 * @property {import("svelte").Snippet} [children]
	 */

	/** @type {Props} */
	let { header = Header, footer = null, children } = $props();

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
		appState.startPeriodicFetch();

		// Be a good citizen and clean up the timer when exiting our settings.
		return () => appState.stopPeriodicFetch();
	} );
</script>

{#if header}
	{@const HeaderComponent = header}
	<HeaderComponent/>
{/if}
{#if children}
	{@render children()}
{:else}
	<!-- CONTENT GOES HERE -->
{/if}
{#if footer}
	{@const FooterComponent = footer}
	<FooterComponent/>
{/if}
