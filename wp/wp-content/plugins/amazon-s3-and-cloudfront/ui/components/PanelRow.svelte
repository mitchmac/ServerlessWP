<script>
	const classes = $$props.class ? $$props.class : "";

	export let header = false;
	export let footer = false;
	export let gradient = false;
</script>

<div class="panel-row {classes}" class:header class:footer>
	{#if gradient}
		<div class="gradient"></div>
	{/if}
	<slot/>
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
