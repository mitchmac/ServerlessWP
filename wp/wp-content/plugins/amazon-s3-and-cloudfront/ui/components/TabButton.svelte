<script>
	import {strings, urls} from "../js/stores";

	export let active = false;
	export let disabled = false;
	export let icon = "";
	export let iconDesc = "";
	export let text = ""
	export let url = $urls.settings;
</script>

<a
	href={url}
	class="button-tab"
	class:active
	class:btn-disabled={disabled}
	{disabled}
	on:click|preventDefault
>
	{#if icon}
		<img
			src={icon}
			type="image/svg+xml"
			alt={iconDesc}
		>
	{/if}
	{#if text}
		<p>{text}</p>
	{/if}
	{#if active}
		<img
			class="checkmark"
			src="{$urls.assets + 'img/icon/licence-checked.svg'}"
			type="image/svg+xml" alt={$strings.selected_desc}
		>
	{/if}
</a>
