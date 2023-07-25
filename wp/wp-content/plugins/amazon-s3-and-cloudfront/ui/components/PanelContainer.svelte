<script>
	const classes = $$props.class ? $$props.class : "";
</script>

<div class="panel-container {classes}">
	<slot/>
</div>
