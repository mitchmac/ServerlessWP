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

<div class="checkbox" class:locked={disabled} class:disabled>
	<label class="toggle-label" for={name}>
		<input type="checkbox" id={name} bind:checked={checked} {disabled}/>
		{@render children?.()}
	</label>
</div>
