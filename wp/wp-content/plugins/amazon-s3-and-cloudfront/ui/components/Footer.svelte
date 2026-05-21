<script>
	import {onDestroy} from "svelte";
	import {slide} from "svelte/transition";
	import {
		revalidatingSettings,
		settings_changed,
		settings,
		strings,
		appState,
		validationErrors
	} from "../js/stores";
	import {
		scrollNotificationsIntoView
	} from "../js/scrollNotificationsIntoView";
	import Button from "./Button.svelte";

	let { settingsStore = settings, settingsChangedStore = settings_changed, onRouteEvent } = $props();

	let saving = $state( false );

	let disabled = $derived( saving || $validationErrors.size > 0 );

	// On init, start with no validation errors.
	validationErrors.set( new Map() );

	/**
	 * Handles a Cancel button click.
	 */
	function handleCancel() {
		settingsStore.reset();
	}

	/**
	 * Handles a Save button click.
	 *
	 * @return {Promise<void>}
	 */
	async function handleSave() {
		saving = true;
		appState.pausePeriodicFetch();
		const result = await settingsStore.save();
		$revalidatingSettings = true;
		const statePromise = appState.resumePeriodicFetch();

		// The save happened, whether anything changed or not.
		if ( result.hasOwnProperty( "saved" ) && result.hasOwnProperty( "changed_settings" ) ) {
			onRouteEvent( { event: "settings.save", data: result } );
		}

		// After save make sure notifications are eyeballed.
		scrollNotificationsIntoView();
		saving = false;

		// Just make sure periodic state fetch promise is done with,
		// even though we don't really care about it.
		await statePromise;
		$revalidatingSettings = false;
	}

	// On navigation away from a component showing the footer,
	// make sure settings are reset.
	onDestroy( () => settingsStore.reset() );
</script>

{#if $settingsChangedStore}
	<div class="fixed-cta-block" transition:slide>
		<div class="buttons">
			<Button outline onclick={handleCancel}>{$strings.cancel_button}</Button>
			<Button primary onclick={handleSave} {disabled}>{$strings.save_changes}</Button>
		</div>
	</div>
{/if}
