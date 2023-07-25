<script>
	import {createEventDispatcher} from "svelte";

	import {urls} from "../js/stores";

	const classes = $$props.class ? $$props.class : "";
	const dispatch = createEventDispatcher();

	export let ref = {};

	// Button sizes, medium is the default.
	export let extraSmall = false;
	export let small = false;
	export let large = false;
	export let medium = !extraSmall && !small && !large;

	// Button styles, outline is the default.
	export let primary = false;
	export let expandable = false;
	export let refresh = false;
	export let outline = !primary && !expandable && !refresh;

	// Is the button disabled? Defaults to false.
	export let disabled = false;

	// Is the button in an expanded state? Defaults to false.
	export let expanded = false;

	// Is the button in a refreshing state? Defaults to false.
	export let refreshing = false;

	// A button can have a title, most useful to give a reason when disabled.
	export let title = "";

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

	function refreshIcon( refreshing ) {
		return $urls.assets + 'img/icon/' + (refreshing ? 'refresh-disabled.svg' : 'refresh.svg');
	}
</script>

<button
	on:click|preventDefault
	class:btn-xs={extraSmall}
	class:btn-sm={small}
	class:btn-md={medium}
	class:btn-lg={large}
	class:btn-primary={primary}
	class:btn-outline={outline}
	class:btn-expandable={expandable}
	class:btn-disabled={disabled}
	class:btn-expanded={expanded}
	class:btn-refresh={refresh}
	class:btn-refreshing={refreshing}
	class={classes}
	{title}
	disabled={disabled || refreshing}
	bind:this={ref}
	on:focusout
	on:keyup={handleKeyup}
>
	{#if refresh}
		<img class="icon refresh" class:refreshing src="{refreshIcon(refreshing)}" alt={title}/>
	{/if}
	<slot/>
</button>
