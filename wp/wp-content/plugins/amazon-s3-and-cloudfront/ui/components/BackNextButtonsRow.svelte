<script>
	import {createEventDispatcher} from "svelte";
	import {strings} from "../js/stores";
	import Button from "./Button.svelte";

	const dispatch = createEventDispatcher();

	// Back button is optional but shown by default.
	export let backText = $strings.back;
	export let backDisabled = false;
	export let backTitle = "";
	export let backVisible = false;

	// Skip button is optional, only shown if explicitly made visible.
	export let skipText = $strings.skip;
	export let skipDisabled = false;
	export let skipTitle = "";
	export let skipVisible = false;

	// Next button required.
	export let nextText = $strings.next;
	export let nextDisabled = false;
	export let nextTitle = "";
</script>

<div class="btn-row">
	{#if backVisible}
		<Button
			large
			on:click="{() => dispatch('back')}"
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
			on:click="{() => dispatch('skip')}"
			disabled={skipDisabled}
			title={skipTitle}
		>
			{skipText}
		</Button>
	{/if}
	<Button
		large
		primary
		on:click="{() => dispatch('next')}"
		disabled={nextDisabled}
		title={nextTitle}
	>
		{nextText}
	</Button>
</div>
