<script>
	import {hasContext, getContext} from "svelte";
	import {writable} from "svelte/store";
	import {push} from "svelte-spa-router";
	import {
		delivery_provider,
		settings,
		storage_provider,
		strings,
		urls
	} from "../js/stores";
	import PanelRow from "./PanelRow.svelte";
	import Button from "./Button.svelte";

	// Parent page may want to be locked.
	let settingsLocked = writable( false );

	if ( hasContext( "settingsLocked" ) ) {
		settingsLocked = getContext( "settingsLocked" );
	}

	$: providerType = $settings[ 'delivery-provider' ] === 'storage' ? 'storage' : 'delivery';
	$: providerKey = providerType === 'storage' ? $storage_provider.provider_key_name : $delivery_provider.provider_key_name;
</script>

<PanelRow header gradient class="delivery {providerType} {providerKey}">
	<img src="{$delivery_provider.icon}" alt={$delivery_provider.provider_service_name}/>
	<div class="provider-details">
		<h3>{$delivery_provider.provider_service_name}</h3>
		<p class="console-details">
			<a href={$urls.delivery_provider_console_url} class="console" target="_blank" title={$strings.view_provider_console}>{$delivery_provider.console_title}</a>
		</p>
	</div>
	<Button outline on:click={() => push('/delivery/provider')} title={$strings.edit_delivery_provider} disabled={$settingsLocked}>{$strings.edit}</Button>
</PanelRow>

<style>
	:global(#as3cf-settings.wpome div.panel.settings .header) img {
		width: var(--as3cf-settings-ctrl-width);
		height: var(--as3cf-settings-ctrl-width);
	}

	.provider-details {
		display: flex;
		flex-direction: column;
		flex: auto;
		margin-left: var(--as3cf-settings-option-indent);
		z-index: 1;
	}

	:global(#as3cf-settings.wpome div.panel) .provider-details h3 {
		margin-left: 0;
		margin-bottom: 0.5rem;
	}

	:global(#as3cf-settings.wpome div.panel) .console-details {
		display: flex;
		align-items: center;
		font-size: 0.75rem;
	}

	.console-details .console {
		flex: 0 1 min-content;
		overflow: hidden;
		white-space: nowrap;
		text-overflow: ellipsis;
	}

	:global(#as3cf-settings.wpome div.panel) .console-details a[target="_blank"].console:after {
		margin-right: 0;
	}
</style>
