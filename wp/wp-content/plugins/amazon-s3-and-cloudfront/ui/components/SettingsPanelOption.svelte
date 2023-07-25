<script>
	import {getContext, hasContext} from "svelte";
	import {writable} from "svelte/store";
	import {slide} from "svelte/transition";
	import {defined_settings, validationErrors} from "../js/stores";
	import PanelRow from "./PanelRow.svelte";
	import ToggleSwitch from "./ToggleSwitch.svelte";
	import DefinedInWPConfig from "./DefinedInWPConfig.svelte";
	import SettingNotifications from "./SettingNotifications.svelte";

	export let heading = "";
	export let description = "";
	export let placeholder = "";
	export let nested = false;
	export let first = false; // of nested items

	// Toggle and Text may both be used at same time.
	export let toggleName = "";
	export let toggle = false;
	export let textName = "";
	export let text = "";
	export let alwaysShowText = false;

	export let definedSettings = defined_settings;

	/**
	 * Optional validator function.
	 *
	 * @param {string} textValue
	 *
	 * @return {string} Empty if no error
	 */
	export let validator = ( textValue ) => "";

	// Parent page may want to be locked.
	let settingsLocked = writable( false );

	let textDirty = false;

	if ( hasContext( "settingsLocked" ) ) {
		settingsLocked = getContext( "settingsLocked" );
	}

	$: locked = $settingsLocked;
	$: toggleDisabled = $definedSettings.includes( toggleName ) || locked;
	$: textDisabled = $definedSettings.includes( textName ) || locked;

	$: input = ((toggleName && toggle) || !toggleName || alwaysShowText) && textName;
	$: headingName = input ? textName + "-heading" : toggleName;

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

		validationErrors.update( _validationErrors => {
			if ( _validationErrors.has( textName ) && message === "" ) {
				_validationErrors.delete( textName );
			} else if ( message !== "" ) {
				_validationErrors.set( textName, message );
			}

			return _validationErrors;
		} );

		return message;
	}

	function onTextInput() {
		textDirty = true;
	}

	$: validationError = validateText( text, toggle );

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
			<h4 id={headingName} on:click={headingClickHandler} class="toggler" class:toggleDisabled>{heading}</h4>
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
				on:input={onTextInput}
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
			<p class="input-error" transition:slide|local>{validationError}</p>
		{/if}
	{/if}

	{#if toggleName}
		<SettingNotifications settingKey={toggleName}/>
	{/if}

	{#if textName}
		<SettingNotifications settingKey={textName}/>
	{/if}

	<slot/>
</div>

<style>
	.toggler:not(.toggleDisabled) {
		cursor: pointer;
	}
</style>
