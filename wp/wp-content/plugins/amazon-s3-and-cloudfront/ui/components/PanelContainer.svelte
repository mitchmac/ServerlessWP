<script>
	/**
	 * @typedef {Object} Props
	 * @property {import("svelte").Snippet} [children]
	 * @property {string} [class]
	 */

	/** @type {Props} */
	let { children, class: classes = "" } = $props();
</script>

<div class="panel-container {classes}">
	{@render children?.()}
</div>
