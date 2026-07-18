<script>
	import Panel from "../components/Panel.svelte";

	let {
		benefits,
		heading,
		description,
		call_to_action,
		call_to_action_note
	} = $props();
</script>

<Panel name="upsell" class="upsell-panel">
	<div class="branding"></div>
	<div class="content">
		<div class="heading">
			{@render heading?.()}
		</div>

		<div class="description">
			{@render description?.()}
		</div>

		<div class="benefits">
			{#each benefits as benefit}
				<li>
					<img src="{benefit.icon}" alt="{benefit.alt}">
					<span>{benefit.text}</span>
				</li>
			{/each}
		</div>

		<div class="call-to-action">
			{@render call_to_action?.()}
			<div class="note">
				{@render call_to_action_note?.()}
			</div>
		</div>
	</div>
</Panel>

<style>
	.content {
		padding: 1.875rem 2.25rem 1.5rem 2.25rem;
		display: flex;
		flex-direction: column;
	}

	.heading {
		margin-top: 1rem;
		font-weight: 700;
		font-size: 1.125rem;
		line-height: 140%;
	}

	.description {
		margin-top: 1rem;
		color: rgba(56, 54, 55, 0.7);
	}

	.benefits {
		margin-top: 1.7rem;
		color: rgba(56, 54, 55, 0.7);
	}

	.benefits li {
		display: flex;
		align-items: center;
	}

	.benefits img {
		height: 40px;
		margin-left: -5px;
		margin-right: 10px;
	}

	.call-to-action {
		margin-top: 0.7rem;
	}

	.call-to-action .note {
		text-align: center;
	}
</style>
