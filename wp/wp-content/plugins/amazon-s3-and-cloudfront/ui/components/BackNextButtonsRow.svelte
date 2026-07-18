<script>
	import {strings} from "../js/stores";
	import Button from "./Button.svelte";

	/**
	 * @typedef {Object} Props
	 * @property {any} [backText]
	 * @property {boolean} [backDisabled]
	 * @property {string} [backTitle]
	 * @property {boolean} [backVisible]
	 * @property {any} [skipText]
	 * @property {boolean} [skipDisabled]
	 * @property {string} [skipTitle]
	 * @property {boolean} [skipVisible]
	 * @property {any} [nextText]
	 * @property {boolean} [nextDisabled]
	 * @property {string} [nextTitle]
	 * @property {function} [onBack]
	 * @property {function} [onSkip]
	 * @property {function} [onNext]
	 */

	/** @type {Props} */
	let {
		backText = $strings.back,
		backDisabled = false,
		backTitle = "",
		backVisible = false,
		skipText = $strings.skip,
		skipDisabled = false,
		skipTitle = "",
		skipVisible = false,
		nextText = $strings.next,
		nextDisabled = false,
		nextTitle = "",
		onBack = {},
		onSkip = {},
		onNext = {}
	} = $props();

	/**
	 * Handle back request, uses onBack callback if set.
	 */
	function handleBack() {
		if ( typeof onBack === "function" ) {
			onBack();
		}
	}

	/**
	 * Handle skip request, uses onSkip callback if set.
	 */
	function handleSkip() {
		if ( typeof onSkip === "function" ) {
			onSkip();
		}
	}

	/**
	 * Handle next request, uses onNext callback if set.
	 */
	function handleNext() {
		if ( typeof onNext === "function" ) {
			onNext();
		}
	}
</script>

<div class="btn-row">
	{#if backVisible}
		<Button
			large
			onclick={handleBack}
			disabled={backDisabled}
			title={backTitle}
		>
			{backText}
		</Button>
	{/if}
	{#if skipVisible}
		<Button
			large
			outline
			onclick={handleSkip}
			disabled={skipDisabled}
			title={skipTitle}
		>
			{skipText}
		</Button>
	{/if}
	<Button
		large
		primary
		onclick={handleNext}
		disabled={nextDisabled}
		title={nextTitle}
	>
		{nextText}
	</Button>
</div>
