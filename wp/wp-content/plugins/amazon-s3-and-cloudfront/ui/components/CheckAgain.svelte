<script>
	import Button from "./Button.svelte";
	import {
		api,
		revalidatingSettings,
		settings_validation,
		state,
		strings
	} from "../js/stores";
	import {delayMin} from "../js/delay";

	export let section = "";

	$: refreshing = $revalidatingSettings;

	let datetime = new Date( $settings_validation[ section ].timestamp * 1000 ).toString();

	/**
	 * Calls the API to revalidate settings.
	 */
	async function revalidate() {
		let start = Date.now();
		let params = {
			revalidateSettings: true,
			section: section,
		};

		refreshing = true;
		let json = await api.get( "state", params );
		await delayMin( start, 1000 );
		state.updateState( json );
		refreshing = false;
	}
</script>

<div class="check-again">
	{#if !refreshing}
		<Button refresh {refreshing} title={$strings.check_again_desc} on:click={revalidate}>
			{$strings.check_again_title}
		</Button>
	{:else}
		<Button refresh {refreshing} title={$strings.check_again_desc}>
			{$strings.check_again_active}
		</Button>
	{/if}
	<span class="last-update" title="{datetime}">
		{$settings_validation[ section ].last_update}
	</span>
</div>

<style>
	div.check-again {
		display: flex;
		flex-direction: column;
		align-items: flex-end;
		white-space: nowrap;
		min-width: 6rem;
		padding-left: 0.5rem;
		color: var(--as3cf-color-gray-700);
	}

	#as3cf-settings .check-again .last-update {
		padding-right: 2px;
		margin-top: 2px;
	}
</style>
