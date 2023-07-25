<script>
	export let name = "";
	export let checked = false;
	export let disabled = false;
</script>

<div class="toggle-switch" class:locked={disabled}>
	<input
		type="checkbox"
		id={name}
		bind:checked={checked}
		{disabled}
	/>
	<label class="toggle-label" for={name}>
		<slot/>
	</label>
</div>