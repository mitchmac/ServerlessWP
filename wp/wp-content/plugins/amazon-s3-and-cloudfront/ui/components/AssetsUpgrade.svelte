<script>
	import {strings, urls} from "../js/stores";
	import Upsell from "./Upsell.svelte";

	let benefits = [
		{
			icon: $urls.assets + 'img/icon/fonts.svg',
			alt: 'js icon',
			text: $strings.assets_uppsell_benefits.js,
		},
		{
			icon: $urls.assets + 'img/icon/css.svg',
			alt: 'css icon',
			text: $strings.assets_uppsell_benefits.css,
		},
		{
			icon: $urls.assets + 'img/icon/fonts.svg',
			alt: 'fonts icon',
			text: $strings.assets_uppsell_benefits.fonts,
		},
	];
</script>

<Upsell benefits={benefits}>
	<div slot="heading">{$strings.assets_upsell_heading}</div>

	<div slot="description">{@html $strings.assets_upsell_description}</div>

	<a slot="call-to-action" href={$urls.upsell_discount_assets} class="button btn-lg btn-primary">
		<img src={$urls.assets + "img/icon/stars.svg"} alt="stars icon" style="margin-right: 5px;">
		{$strings.assets_upsell_cta}
	</a>
</Upsell>
