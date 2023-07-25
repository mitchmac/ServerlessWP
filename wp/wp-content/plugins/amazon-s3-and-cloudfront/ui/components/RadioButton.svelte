<script>
	export let list = false;
	export let disabled = false;
	export let name = "options";
	export let value = "";
	export let selected = "";
	export let desc = "";
</script>

<div class="radio-btn" class:list class:disabled>
	<label>
		<input type="radio" {name} bind:group={selected} {value} {disabled}>
		<slot/>
	</label>
</div>
{#if selected === value && desc}
	<p class="radio-desc">
		{@html desc}
	</p>
{/if}