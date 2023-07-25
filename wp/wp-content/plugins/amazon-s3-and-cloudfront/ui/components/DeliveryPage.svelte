<script>
	import {createEventDispatcher, setContext} from "svelte";
	import {
		strings,
		settings,
		storage_provider,
		delivery_providers,
		delivery_provider,
		defined_settings,
		settingsLocked,
		current_settings,
		needs_refresh,
		revalidatingSettings,
		state
	} from "../js/stores";
	import {
		scrollNotificationsIntoView
	} from "../js/scrollNotificationsIntoView";
	import {needsRefresh} from "../js/needsRefresh";
	import Page from "./Page.svelte";
	import Notifications from "./Notifications.svelte";
	import Panel from "./Panel.svelte";
	import PanelRow from "./PanelRow.svelte";
	import TabButton from "./TabButton.svelte";
	import BackNextButtonsRow from "./BackNextButtonsRow.svelte";
	import HelpButton from "./HelpButton.svelte";

	const dispatch = createEventDispatcher();

	export let name = "delivery-provider";
	export let params = {}; // Required for regex routes.
	const _params = params; // Stops compiler warning about unused params export;

	// Let all child components know if settings are currently locked.
	setContext( "settingsLocked", settingsLocked );

	// As this page does not directly alter the settings store until done,
	// we need to keep track of any changes made elsewhere and prompt
	// the user to refresh the page.
	let saving = false;
	const previousSettings = { ...$current_settings };
	const previousDefines = { ...$defined_settings };

	$: {
		$needs_refresh = $needs_refresh || needsRefresh( saving, previousSettings, $current_settings, previousDefines, $defined_settings );
	}

	// Start with a copy of the current delivery provider.
	let deliveryProvider = { ...$delivery_provider };

	$: defined = $defined_settings.includes( "delivery-provider" );
	$: disabled = defined || $settingsLocked;

	let serviceName = $settings[ "delivery-provider-service-name" ];

	$: serviceNameDefined = $defined_settings.includes( "delivery-provider-service-name" );
	$: serviceNameDisabled = serviceNameDefined || $settingsLocked;

	/**
	 * Returns an array of delivery providers that can be used with the currently configured storage provider.
	 *
	 * @return {array}
	 */
	function supportedDeliveryProviders() {
		return Object.values( $delivery_providers ).filter(
			( provider ) => provider.supported_storage_providers.length === 0 || provider.supported_storage_providers.includes( $storage_provider.provider_key_name )
		);
	}

	/**
	 * Determines whether the Next button should be disabled or not and returns a suitable reason.
	 *
	 * @param {Object} provider
	 * @param {string} providerName
	 * @param {boolean} settingsLocked
	 * @param {boolean} needsRefresh
	 *
	 * @return {string}
	 */
	function getNextDisabledMessage( provider, providerName, settingsLocked, needsRefresh ) {
		let message = "";

		if ( settingsLocked || needsRefresh ) {
			message = $strings.settings_locked;
		} else if ( provider.provider_service_name_override_allowed && providerName.trim().length < 1 ) {
			message = $strings.no_delivery_provider_name;
		} else if ( provider.provider_service_name_override_allowed && providerName.trim().length < 4 ) {
			message = $strings.delivery_provider_name_short;
		} else if ( deliveryProvider.provider_key_name === $delivery_provider.provider_key_name && providerName === $settings[ "delivery-provider-service-name" ] ) {
			message = $strings.nothing_to_save;
		}

		return message;
	}

	$: nextDisabledMessage = getNextDisabledMessage( deliveryProvider, serviceName, $settingsLocked, $needs_refresh );

	/**
	 * Handles choosing a different delivery provider.
	 *
	 * @param {Object} provider
	 */
	function handleChooseProvider( provider ) {
		if ( disabled ) {
			return;
		}

		deliveryProvider = provider;
	}

	/**
	 * Handles a Next button click.
	 *
	 * @return {Promise<void>}
	 */
	async function handleNext() {
		saving = true;
		state.pausePeriodicFetch();

		$settings[ "delivery-provider" ] = deliveryProvider.provider_key_name;
		$settings[ "delivery-provider-service-name" ] = serviceName;
		const result = await settings.save();

		// If something went wrong, don't move onto next step.
		if ( result.hasOwnProperty( "saved" ) && !result.saved ) {
			settings.reset();
			saving = false;
			await state.resumePeriodicFetch();

			scrollNotificationsIntoView();

			return;
		}

		$revalidatingSettings = true;
		const statePromise = state.resumePeriodicFetch();

		dispatch( "routeEvent", {
			event: "settings.save",
			data: result,
			default: "/media/delivery"
		} );

		// Just make sure periodic state fetch promise is done with,
		// even though we don't really care about it.
		await statePromise;
		$revalidatingSettings = false;
	}
</script>

<Page {name} subpage on:routeEvent>
	<Notifications tab={name} tabParent="media"/>
	<h2 class="page-title">{$strings.delivery_title}</h2>

	<div class="delivery-provider-settings-page wrapper">
		<Panel heading={$strings.select_delivery_provider_title} defined={defined} multi>
			<PanelRow class="body flex-column delivery-provider-buttons">
				{#each supportedDeliveryProviders() as provider}
					<div class="row">
						<TabButton
							active={provider.provider_key_name === deliveryProvider.provider_key_name}
							{disabled}
							icon={provider.icon}
							text={provider.default_provider_service_name}
							on:click={() => handleChooseProvider( provider )}
						/>
						<p class="speed">{@html provider.edge_server_support_desc}</p>
						<p class="private-media">{@html provider.signed_urls_support_desc}</p>
						<HelpButton url={provider.provider_service_quick_start_url} desc={$strings.view_quick_start_guide}/>
					</div>
				{/each}
			</PanelRow>
		</Panel>

		{#if deliveryProvider.provider_service_name_override_allowed}
			<Panel heading={$strings.enter_other_cdn_name_title} defined={serviceNameDefined} multi>
				<PanelRow class="body flex-column">
					<input
						type="text"
						class="cdn-name"
						id="cdn-name"
						name="cdn-name"
						minlength="4"
						placeholder={$strings.enter_other_cdn_name_placeholder}
						bind:value={serviceName}
						disabled={serviceNameDisabled}
					>
				</PanelRow>
			</Panel>
		{/if}

		<BackNextButtonsRow
			on:next={handleNext}
			nextText={$strings.save_delivery_provider}
			nextDisabled={nextDisabledMessage}
			nextTitle={nextDisabledMessage}
		/>
	</div>
</Page>
