<script>
	import {getContext, hasContext} from "svelte";
	import {writable} from "svelte/store";
	import {slide} from "svelte/transition";
	import {defined_settings, validationErrors} from "../js/stores";
	import PanelRow from "./PanelRow.svelte";
	import ToggleSwitch from "./ToggleSwitch.svelte";
	import DefinedInWPConfig from "./DefinedInWPConfig.svelte";
	import SettingNotifications from "./SettingNotifications.svelte";

	/**
	 * @typedef {Object} Props
	 * @property {string} [heading]
	 * @property {string} [description]
	 * @property {string} [placeholder]
	 * @property {boolean} [nested]
	 * @property {boolean} [first]
	 * @property {string} [toggleName]
	 * @property {boolean} [toggle]
	 * @property {string} [textName]
	 * @property {string} [text]
	 * @property {boolean} [alwaysShowText]
	 * @property {any} [definedSettings]
	 * @property {any} [validator] - Optional validator function.
	 * @property {import("svelte").Snippet} [children]
	 */

	/** @type {Props} */
	let {
		heading = "",
		description = "",
		placeholder = "",
		nested = false,
		first = false,
		toggleName = "",
		toggle = $bindable( false ),
		textName = "",
		text = $bindable( "" ),
		alwaysShowText = false,
		definedSettings = defined_settings,
		validator = ( textValue ) => "",
		children
	} = $props();

	// Parent page may want to be locked.
	let settingsLocked = $state( writable( false ) );

	let textDirty = $state( false );

	if ( hasContext( "settingsLocked" ) ) {
		settingsLocked = getContext( "settingsLocked" );
	}

	let locked = $derived( $settingsLocked );
	let toggleDisabled = $derived( $definedSettings.includes( toggleName ) || locked );
	let textDisabled = $derived( $definedSettings.includes( textName ) || locked );

	let input = $derived( ((toggleName && toggle) || !toggleName || alwaysShowText) && textName );
	let headingName = $derived( input ? textName + "-heading" : toggleName );

	/**
	 * Validate the text if validator function supplied.
	 *
	 * @param {string} text
	 * @param {bool} toggle
	 *
	 * @return {string}
	 */
	function validateText( text, toggle ) {
		let message = "";

		if ( validator !== undefined && toggle && !textDisabled ) {
			message = validator( text );
		}

		return message;
	}

	function onTextInput() {
		textDirty = true;
	}

	let validationError = $derived( validateText( text, toggle ) );

	/**
	 * Keep validationErrors store up to date as validationError changes.
	 */
	$effect( () => {
		validationErrors.update( _validationErrors => {
			if ( _validationErrors.has( textName ) && validationError === "" ) {
				_validationErrors.delete( textName );
			} else if ( validationError !== "" ) {
				_validationErrors.set( textName, validationError );
			}

			return _validationErrors;
		} );
	} );

	/**
	 * If appropriate, clicking the header toggles to toggle switch.
	 */
	function headingClickHandler() {
		if ( toggleName && !toggleDisabled ) {
			toggle = !toggle;
		}
	}
</script>

<div class="setting" class:nested class:first>
	<PanelRow class="option">
		{#if toggleName}
			<ToggleSwitch name={toggleName} bind:checked={toggle} disabled={toggleDisabled}>
				{heading}
			</ToggleSwitch>
			<!-- TODO: Fix a11y. -->
			<!-- svelte-ignore a11y_no_noninteractive_element_interactions -->
			<!-- svelte-ignore a11y_click_events_have_key_events -->
			<h4 id={headingName} onclick={headingClickHandler} class="toggler" class:toggleDisabled>{heading}</h4>
		{:else}
			<h4 id={headingName}>{heading}</h4>
		{/if}
		<DefinedInWPConfig defined={$definedSettings.includes( toggleName ) || (input && $definedSettings.includes( textName ))}/>
	</PanelRow>
	<PanelRow class="desc">
		<p>{@html description}</p>
	</PanelRow>
	{#if input}
		<PanelRow class="input">
			<input
				type="text"
				id={textName}
				name={textName}
				bind:value={text}
				oninput={onTextInput}
				minlength="1"
				size="10"
				{placeholder}
				disabled={textDisabled}
				class:disabled={textDisabled}
				aria-labelledby={headingName}
			>
			<label for={textName}>
				{heading}
			</label>
		</PanelRow>
		{#if validationError && textDirty}
			<p class="input-error" transition:slide>{validationError}</p>
		{/if}
	{/if}

	{#if toggleName}
		<SettingNotifications settingKey={toggleName}/>
	{/if}

	{#if textName}
		<SettingNotifications settingKey={textName}/>
	{/if}

	{@render children?.()}
</div>

<style>
	.toggler:not(.toggleDisabled) {
		cursor: pointer;
	}
</style>
