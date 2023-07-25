<script>
	export let name = "";
	export let checked = false;
	export let disabled = false;
</script>

<div class="checkbox" class:locked={disabled} class:disabled>
	<label class="toggle-label" for={name}>
		<input type="checkbox" id={name} bind:checked={checked} {disabled}/>
		<slot/>
	</label>
</div>