<script>
	/**
	 * @typedef {Object} Props
	 * @property {boolean} [list]
	 * @property {boolean} [disabled]
	 * @property {string} [name]
	 * @property {string} [value]
	 * @property {string} [selected]
	 * @property {string} [desc]
	 * @property {import("svelte").Snippet} [children]
	 */

	/** @type {Props} */
	let {
		list = false,
		disabled = false,
		name = "options",
		value = "",
		selected = $bindable( "" ),
		desc = "",
		children
	} = $props();
</script>

<div class="radio-btn" class:list class:disabled>
	<label>
		<input type="radio" {name} bind:group={selected} {value} {disabled}>
		{@render children?.()}
	</label>
</div>
{#if selected === value && desc}
	<p class="radio-desc">
		{@html desc}
	</p>
{/if}
