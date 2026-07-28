<script>
	import {urls} from "../js/stores";

	/**
	 * @typedef {Object} Props
	 * @property {any} [ref]
	 * @property {boolean} [extraSmall] - Button sizes, medium is the default.
	 * @property {boolean} [small]
	 * @property {boolean} [large]
	 * @property {any} [medium]
	 * @property {boolean} [primary] - Button styles, outline is the default.
	 * @property {boolean} [expandable]
	 * @property {boolean} [refresh]
	 * @property {any} [outline]
	 * @property {boolean} [disabled] - Is the button disabled? Defaults to false.
	 * @property {boolean} [expanded] - Is the button in an expanded state? Defaults to false.
	 * @property {boolean} [refreshing] - Is the button in a refreshing state? Defaults to false.
	 * @property {string} [title] - A button can have a title, most useful to give a reason when disabled.
	 * @property {import("svelte").Snippet} [children]
	 * @property {string} [class]
	 * @property {function} [onclick]
	 * @property {function} [onfocusout]
	 * @property {function} [onCancel] - Callback for custom cancel event.
	 */

	/** @type {Props} */
	let {
		ref = $bindable( {} ),
		extraSmall = false,
		small = false,
		large = false,
		medium = !extraSmall && !small && !large,
		primary = false,
		expandable = false,
		refresh = false,
		outline = !primary && !expandable && !refresh,
		disabled = false,
		expanded = false,
		refreshing = false,
		title = "",
		children,
		class: classes = "",
		onclick,
		onfocusout,
		onCancel = {}
	} = $props();

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

	function refreshIcon( refreshing ) {
		return $urls.assets + "img/icon/" + (refreshing ? "refresh-disabled.svg" : "refresh.svg");
	}
</script>

<button
	{onclick}
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
	{onfocusout}
	onkeyup={handleKeyup}
>
	{#if refresh}
		<img class="icon refresh" class:refreshing src="{refreshIcon(refreshing)}" alt={title}/>
	{/if}
	{@render children?.()}
</button>
