<script>
	/**
	 * @typedef {Object} Props
	 * @property {boolean} [header]
	 * @property {boolean} [footer]
	 * @property {boolean} [gradient]
	 * @property {import("svelte").Snippet} [children]
	 * @property {string} [class]
	 */

	/** @type {Props} */
	let {
		header = false,
		footer = false,
		gradient = false,
		children,
		class: classes = ""
	} = $props();
</script>

<div class="panel-row {classes}" class:header class:footer>
	{#if gradient}
		<div class="gradient"></div>
	{/if}
	{@render children?.()}
</div>

<style>
	.panel-row {
		position: relative;
	}

	.header .gradient {
		position: absolute;
		width: 144px;
		left: 0;
		top: 0;
		bottom: 0;
		transform: matrix(-1, 0, 0, 1, 0, 0);
		border-top-right-radius: 5px;
	}
</style>
