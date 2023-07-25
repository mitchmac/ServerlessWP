<script>
	import {createEventDispatcher, onDestroy} from "svelte";
	import {slide} from "svelte/transition";
	import {
		revalidatingSettings,
		settings_changed,
		settings,
		strings,
		state,
		validationErrors
	} from "../js/stores";
	import {
		scrollNotificationsIntoView
	} from "../js/scrollNotificationsIntoView";
	import Button from "./Button.svelte";

	const dispatch = createEventDispatcher();

	export let settingsStore = settings;
	export let settingsChangedStore = settings_changed;

	let saving = false;

	$: disabled = saving || $validationErrors.size > 0;

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
		state.pausePeriodicFetch();
		const result = await settingsStore.save();
		$revalidatingSettings = true;
		const statePromise = state.resumePeriodicFetch();

		// The save happened, whether anything changed or not.
		if ( result.hasOwnProperty( "saved" ) && result.hasOwnProperty( "changed_settings" ) ) {
			dispatch( "routeEvent", { event: "settings.save", data: result } );
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
	onDestroy( () => handleCancel() );
</script>

{#if $settingsChangedStore}
	<div class="fixed-cta-block" transition:slide|local>
		<div class="buttons">
			<Button outline on:click={handleCancel}>{$strings.cancel_button}</Button>
			<Button primary on:click={handleSave} {disabled}>{$strings.save_changes}</Button>
		</div>
	</div>
{/if}
