<script>
	import {createEventDispatcher, getContext, hasContext} from "svelte";
	import {writable} from "svelte/store";
	import {fade} from "svelte/transition";
	import {link} from "svelte-spa-router";
	import {defined_settings, strings} from "../js/stores";
	import PanelContainer from "./PanelContainer.svelte";
	import PanelRow from "./PanelRow.svelte";
	import DefinedInWPConfig from "./DefinedInWPConfig.svelte";
	import ToggleSwitch from "./ToggleSwitch.svelte";
	import HelpButton from "./HelpButton.svelte";
	import Button from "./Button.svelte";

	const classes = $$props.class ? $$props.class : "";
	const dispatch = createEventDispatcher();

	export let ref = {};
	export let name = "";
	export let heading = "";
	export let defined = false;
	export let multi = false;
	export let flyout = false;
	export let toggleName = "";
	export let toggle = false;
	export let refresh = false;
	export let refreshText = $strings.refresh_title;
	export let refreshDesc = refreshText;
	export let refreshing = false;
	export let helpKey = "";
	export let helpURL = "";
	export let helpDesc = $strings.help_desc;

	// We can display storage provider info on the right-hand side of the panel's header.
	// In the future, if anything else needs to be displayed in the same position we
	// should create a named slot or assignable component. CSS changes would be required.
	export let storageProvider = null;

	// Parent page may want to be locked.
	let settingsLocked = writable( false );

	if ( hasContext( "settingsLocked" ) ) {
		settingsLocked = getContext( "settingsLocked" );
	}

	$: locked = $settingsLocked;
	$: toggleDisabled = $defined_settings.includes( toggleName ) || locked;

	/**
	 * If appropriate, clicking the header toggles to toggle switch.
	 */
	function headingClickHandler() {
		if ( toggleName && !toggleDisabled ) {
			toggle = !toggle;
		}
	}

	/**
	 * Catch escape key and emit a custom cancel event.
	 *
	 * @param {KeyboardEvent} event
	 */
	function handleKeyup( event ) {
		if ( event.key === "Escape" ) {
			event.preventDefault();
			dispatch( "cancel" );
		}
	}
</script>

<div
	class="panel {name}"
	class:multi
	class:flyout
	class:locked
	transition:fade|local={{duration: flyout ? 200 : 0}}
	bind:this={ref}
	on:focusout
	on:mouseenter
	on:mouseleave
	on:mousedown
	on:click
	on:keyup={handleKeyup}
>
	{#if !multi && heading}
		<div class="heading">
			<h2>{heading}</h2>
			{#if helpURL}
				<HelpButton url={helpURL} desc={helpDesc}/>
			{:else if helpKey}
				<HelpButton key={helpKey} desc={helpDesc}/>
			{/if}
			<DefinedInWPConfig {defined}/>
		</div>
	{/if}
	<PanelContainer class={classes}>
		{#if multi && heading}
			<PanelRow header>
				{#if toggleName}
					<ToggleSwitch name={toggleName} bind:checked={toggle} disabled={toggleDisabled}>
						{heading}
					</ToggleSwitch>
					<h3 on:click={headingClickHandler} class="toggler" class:toggleDisabled>{heading}</h3>
				{:else}
					<h3>{heading}</h3>
				{/if}
				<DefinedInWPConfig {defined}/>
				{#if refresh}
					<Button refresh {refreshing} title={refreshDesc} on:click={() => dispatch("refresh")}>{@html refreshText}</Button>
				{/if}
				{#if storageProvider}
					<div class="provider">
						<a href="/storage/provider" use:link class="link">
							<img src={storageProvider.link_icon} alt={storageProvider.icon_desc}>
							{storageProvider.provider_service_name}
						</a>
					</div>
				{/if}
				{#if helpURL}
					<HelpButton url={helpURL} desc={helpDesc}/>
				{:else if helpKey}
					<HelpButton key={helpKey} desc={helpDesc}/>
				{/if}
			</PanelRow>
		{/if}

		<slot/>
	</PanelContainer>
</div>

<style>
	.toggler:not(.toggleDisabled) {
		cursor: pointer;
	}
</style>
