<script>
	import {strings, urls} from "../js/stores";

	/**
	 * @typedef {Object} Props
	 * @property {boolean} [active]
	 * @property {boolean} [disabled]
	 * @property {string} [icon]
	 * @property {string} [iconDesc]
	 * @property {string} [text]
	 * @property {string} [url]
	 * @property {function} [onclick]
	 */

	/** @type {Props} */
	let {
		active = false,
		disabled = false,
		icon = "",
		iconDesc = "",
		text = "",
		url = $urls.settings,
		onclick
	} = $props();
</script>

<a
	href={url}
	class="button-tab"
	class:active
	class:btn-disabled={disabled}
	{disabled}
	{onclick}
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
