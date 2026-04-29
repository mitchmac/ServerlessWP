<script>
	import {strings, urls} from "../js/stores";
	import Upsell from "./Upsell.svelte";

	let benefits = [
		{
			icon: $urls.assets + "img/icon/fonts.svg",
			alt: "js icon",
			text: $strings.assets_upsell_benefits.js,
		},
		{
			icon: $urls.assets + "img/icon/css.svg",
			alt: "css icon",
			text: $strings.assets_upsell_benefits.css,
		},
		{
			icon: $urls.assets + "img/icon/fonts.svg",
			alt: "fonts icon",
			text: $strings.assets_upsell_benefits.fonts,
		},
	];
</script>

<Upsell benefits={benefits}>
	{#snippet heading()}
		<div>{$strings.assets_upsell_heading}</div>
	{/snippet}

	{#snippet description()}
		<div>{@html $strings.assets_upsell_description}</div>
	{/snippet}

	{#snippet call_to_action()}
		<a href={$urls.upsell_discount_assets} class="button btn-lg btn-primary">
			<img src={$urls.assets + "img/icon/stars.svg"} alt="stars icon" style="margin-right: 5px;">
			{$strings.assets_upsell_cta}
		</a>
	{/snippet}
</Upsell>
