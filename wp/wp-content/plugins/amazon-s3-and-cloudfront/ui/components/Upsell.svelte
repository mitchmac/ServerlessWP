<script>
	import Panel from "../components/Panel.svelte";

	export let benefits;
</script>

<Panel name="upsell" class="upsell-panel">
	<div class="branding"></div>
	<div class="content">
		<div class="heading">
			<slot name="heading"></slot>
		</div>

		<div class="description">
			<slot name="description"></slot>
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
			<slot name="call-to-action"></slot>
			<div class="note">
				<slot name="call-to-action-note"></slot>
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
