<script>
	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {boolean} [checked]
	 * @property {boolean} [disabled]
	 * @property {import("svelte").Snippet} [children]
	 */

	/** @type {Props} */
	let {
		name = "",
		checked = $bindable( false ),
		disabled = false,
		children
	} = $props();
</script>

<div class="toggle-switch" class:locked={disabled}>
	<input
		type="checkbox"
		id={name}
		bind:checked={checked}
		{disabled}
	/>
	<label class="toggle-label" for={name}>
		{@render children?.()}
	</label>
</div>
