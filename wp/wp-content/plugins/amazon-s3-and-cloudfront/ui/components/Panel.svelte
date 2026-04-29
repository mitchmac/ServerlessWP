<script>
	import {getContext, hasContext} from "svelte";
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

	/**
	 * @typedef {Object} Props
	 * @property {any} [ref]
	 * @property {string} [name]
	 * @property {string} [heading]
	 * @property {boolean} [defined]
	 * @property {boolean} [multi]
	 * @property {boolean} [flyout]
	 * @property {string} [toggleName]
	 * @property {boolean} [toggle]
	 * @property {boolean} [refresh]
	 * @property {any} [refreshText]
	 * @property {any} [refreshDesc]
	 * @property {boolean} [refreshing]
	 * @property {string} [helpKey]
	 * @property {string} [helpURL]
	 * @property {any} [helpDesc]
	 * @property {any} [storageProvider]
	 * @property {import("svelte").Snippet} [children]
	 * @property {string} [class]
	 * @property {function} [onclick]
	 * @property {function} [onfocusout]
	 * @property {function} [onmouseenter]
	 * @property {function} [onmouseleave]
	 * @property {function} [onmousedown]
	 * @property {function} [onCancel]
	 * @property {function} [onRefresh]
	 */

	/** @type {Props} */
	let {
		ref = $bindable( {} ),
		name = "",
		heading = "",
		defined = false,
		multi = false,
		flyout = false,
		toggleName = "",
		toggle = $bindable( false ),
		refresh = false,
		refreshText = $strings.refresh_title,
		refreshDesc = refreshText,
		refreshing = false,
		helpKey = "",
		helpURL = "",
		helpDesc = $strings.help_desc,
		storageProvider = null,
		children,
		class: classes = "",
		onclick,
		onfocusout,
		onmouseenter,
		onmouseleave,
		onmousedown,
		onCancel = {},
		onRefresh = {}
	} = $props();

	// Parent page may want to be locked.
	let settingsLocked = $state( writable( false ) );

	if ( hasContext( "settingsLocked" ) ) {
		settingsLocked = getContext( "settingsLocked" );
	}

	let locked = $derived( $settingsLocked );
	let toggleDisabled = $derived( $defined_settings.includes( toggleName ) || locked );

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
		if ( event.key === "Escape" && typeof onCancel === "function" ) {
			event.preventDefault();
			onCancel();
		}
	}

	/**
	 * Handle refresh request, uses onRefresh callback if set.
	 */
	function handleRefresh() {
		if ( typeof onRefresh === "function" ) {
			onRefresh();
		}
	}
</script>

<!-- TODO: Fix a11y. -->
<!-- svelte-ignore a11y_no_static_element_interactions -->
<div
	class="panel {name}"
	class:multi
	class:flyout
	class:locked
	transition:fade={{duration: flyout ? 200 : 0}}
	bind:this={ref}
	{onfocusout}
	{onmouseenter}
	{onmouseleave}
	{onmousedown}
	{onclick}
	onkeyup={handleKeyup}
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
					<!-- TODO: Fix a11y. -->
					<!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
					<!-- svelte-ignore a11y_click_events_have_key_events -->
					<h3 onclick={headingClickHandler} class="toggler" class:toggleDisabled>{heading}</h3>
				{:else}
					<h3>{heading}</h3>
				{/if}
				<DefinedInWPConfig {defined}/>
				{#if refresh}
					<Button refresh {refreshing} title={refreshDesc} onclick={handleRefresh}>{@html refreshText}</Button>
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

		{@render children?.()}
	</PanelContainer>
</div>

<style>
	.toggler:not(.toggleDisabled) {
		cursor: pointer;
	}
</style>
