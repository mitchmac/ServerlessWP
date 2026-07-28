<script>
	import {strings, urls} from "../js/stores";
	import Upsell from "./Upsell.svelte";

	let benefits = [
		{
			icon: $urls.assets + "img/icon/offload-remaining.svg",
			alt: "offload icon",
			text: $strings.tools_upsell_benefits.offload,
		},
		{
			icon: $urls.assets + "img/icon/download.svg",
			alt: "download icon",
			text: $strings.tools_upsell_benefits.download,
		},
		{
			icon: $urls.assets + "img/icon/remove-from-bucket.svg",
			alt: "remove from bucket icon",
			text: $strings.tools_upsell_benefits.remove_bucket,
		},
		{
			icon: $urls.assets + "img/icon/remove-from-server.svg",
			alt: "remove from server icon",
			text: $strings.tools_upsell_benefits.remove_server,
		},
	];
</script>

<Upsell benefits={benefits}>
	{#snippet heading()}
		<div>{$strings.tools_upsell_heading}</div>
	{/snippet}

	{#snippet description()}
		<div>{@html $strings.tools_upsell_description}</div>
	{/snippet}

	{#snippet call_to_action()}
		<a href={$urls.upsell_discount_tools} class="button btn-lg btn-primary">
			<img src={$urls.assets + "img/icon/stars.svg"} alt="stars icon" style="margin-right: 5px;">
			{$strings.tools_upsell_cta}
		</a>
	{/snippet}
</Upsell>
