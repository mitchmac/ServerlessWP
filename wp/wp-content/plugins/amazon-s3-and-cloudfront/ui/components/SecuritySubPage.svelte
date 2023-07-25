<script>
	import {createEventDispatcher, getContext, hasContext} from "svelte";
	import {writable} from "svelte/store";
	import {slide} from "svelte/transition";
	import {
		api,
		settings,
		strings,
		current_settings,
		storage_provider,
		delivery_provider,
		needs_refresh,
		revalidatingSettings,
		state,
		defined_settings
	} from "../js/stores";
	import {
		scrollNotificationsIntoView
	} from "../js/scrollNotificationsIntoView";
	import {needsRefresh} from "../js/needsRefresh";
	import SubPage from "./SubPage.svelte";
	import Panel from "./Panel.svelte";
	import PanelRow from "./PanelRow.svelte";
	import BackNextButtonsRow from "./BackNextButtonsRow.svelte";
	import Checkbox from "./Checkbox.svelte";

	const dispatch = createEventDispatcher();

	// Parent page may want to be locked.
	let settingsLocked = writable( false );

	if ( hasContext( "settingsLocked" ) ) {
		settingsLocked = getContext( "settingsLocked" );
	}

	// As this page does not directly alter the settings store until done,
	// we need to keep track of any changes made elsewhere and prompt
	// the user to refresh the page.
	let saving = false;
	const previousSettings = { ...$current_settings };
	const previousDefines = { ...$defined_settings };

	$: {
		$needs_refresh = $needs_refresh || needsRefresh( saving, previousSettings, $current_settings, previousDefines, $defined_settings );
	}

	let blockPublicAccess = $settings[ "block-public-access" ];
	let bapaSetupConfirmed = false;

	let objectOwnershipEnforced = $settings[ "object-ownership-enforced" ];
	let ooeSetupConfirmed = false;

	// During initial setup we show a slightly different page
	// if ACLs disabled but unsupported by Delivery Provider.
	let initialSetup = false;

	if ( hasContext( "initialSetup" ) ) {
		initialSetup = getContext( "initialSetup" );
	}

	// If provider has changed, then still treat as initial setup.
	if (
		!initialSetup &&
		hasContext( "initialSettings" ) &&
		getContext( "initialSettings" ).provider !== $current_settings.provider
	) {
		initialSetup = true;
	}

	/**
	 * Calls API to update the properties of the current bucket.
	 *
	 * @return {Promise<boolean|*>}
	 */
	async function updateBucketProperties() {
		let data = await api.put( "buckets", {
			bucket: $settings.bucket,
			blockPublicAccess: blockPublicAccess,
			objectOwnershipEnforced: objectOwnershipEnforced
		} );

		if ( data.hasOwnProperty( "saved" ) ) {
			return data.saved;
		}

		return false;
	}

	/**
	 * Returns text to be displayed on Next button.
	 *
	 * @param {boolean} bapaCurrent
	 * @param {boolean} bapaNew
	 * @param {boolean} ooeCurrent
	 * @param {boolean} ooeNew
	 * @param {boolean} needsRefresh
	 * @param {boolean} settingsLocked
	 *
	 * @return {string}
	 */
	function getNextText( bapaCurrent, bapaNew, ooeCurrent, ooeNew, needsRefresh, settingsLocked ) {
		if ( needsRefresh || settingsLocked ) {
			return $strings.settings_locked;
		}

		if ( bapaCurrent !== bapaNew || ooeCurrent !== ooeNew ) {
			return $strings.update_bucket_security;
		}

		return $strings.keep_bucket_security;
	}

	$: nextText = getNextText(
		$current_settings[ "block-public-access" ],
		blockPublicAccess,
		$current_settings[ "object-ownership-enforced" ],
		objectOwnershipEnforced,
		$needs_refresh,
		$settingsLocked
	);

	/**
	 * Determines whether the Next button should be disabled or not.
	 *
	 * If the delivery provider supports the security setting, then do not enable it until setup confirmed.
	 *
	 * All other scenarios result in safe results or warned against repercussions that are being explicitly ignored.
	 *
	 * @param {boolean} currentValue
	 * @param {boolean} newValue
	 * @param {boolean} supported
	 * @param {boolean} setupConfirmed
	 * @param {boolean} needsRefresh
	 * @param {boolean} settingsLocked
	 *
	 * @returns {boolean}
	 */
	function getNextDisabled( currentValue, newValue, supported, setupConfirmed, needsRefresh, settingsLocked ) {
		return needsRefresh || settingsLocked || (!currentValue && newValue && supported && !setupConfirmed);
	}

	$: nextDisabled =
		getNextDisabled(
			$current_settings[ "block-public-access" ],
			blockPublicAccess,
			$delivery_provider.block_public_access_supported,
			bapaSetupConfirmed,
			$needs_refresh,
			$settingsLocked
		) ||
		getNextDisabled(
			$current_settings[ "object-ownership-enforced" ],
			objectOwnershipEnforced,
			$delivery_provider.object_ownership_supported,
			ooeSetupConfirmed,
			$needs_refresh,
			$settingsLocked
		);

	/**
	 * Handles a Next button click.
	 *
	 * @return {Promise<void>}
	 */
	async function handleNext() {
		if (
			blockPublicAccess === $current_settings[ "block-public-access" ] &&
			objectOwnershipEnforced === $current_settings[ "object-ownership-enforced" ]
		) {
			dispatch( "routeEvent", { event: "next", default: "/" } );
			return;
		}

		saving = true;
		state.pausePeriodicFetch();

		const result = await updateBucketProperties();

		// Regardless of whether update succeeded or not, make sure settings are up-to-date.
		await settings.fetch();

		if ( false === result ) {
			saving = false;
			await state.resumePeriodicFetch();

			scrollNotificationsIntoView();
			return;
		}

		$revalidatingSettings = true;
		const statePromise = state.resumePeriodicFetch();

		// Block All Public Access changed.
		dispatch( "routeEvent", {
			event: "bucket-security",
			data: {
				blockPublicAccess: $settings[ "block-public-access" ],
				objectOwnershipEnforced: $settings[ "object-ownership-enforced" ]
			},
			default: "/"
		} );

		// Just make sure periodic state fetch promise is done with,
		// even though we don't really care about it.
		await statePromise;
		$revalidatingSettings = false;
	}
</script>

<SubPage name="bapa-settings" route="/storage/security">
	<Panel
		class="toggle-header"
		heading={$strings.block_public_access_title}
		toggleName="block-public-access"
		bind:toggle={blockPublicAccess}
		helpKey="block-public-access"
		multi
	>
		<PanelRow class="body flex-column">
			{#if initialSetup && $current_settings[ "block-public-access" ] && !$delivery_provider.block_public_access_supported}
				<p>{@html $strings.block_public_access_enabled_setup_sub}</p>
				<p>{@html $delivery_provider.block_public_access_enabled_unsupported_setup_desc} {@html $storage_provider.block_public_access_enabled_unsupported_setup_desc}</p>
			{:else if $current_settings[ "block-public-access" ] && $delivery_provider.block_public_access_supported}
				<p>{@html $strings.block_public_access_enabled_sub}</p>
				<p>{@html $delivery_provider.block_public_access_enabled_supported_desc} {@html $storage_provider.block_public_access_enabled_supported_desc}</p>
			{:else if $current_settings[ "block-public-access" ] && !$delivery_provider.block_public_access_supported}
				<p>{@html $strings.block_public_access_enabled_sub}</p>
				<p>{@html $delivery_provider.block_public_access_enabled_unsupported_desc} {@html $storage_provider.block_public_access_enabled_unsupported_desc}</p>
			{:else if !$current_settings[ "block-public-access" ] && $delivery_provider.block_public_access_supported}
				<p>{@html $strings.block_public_access_disabled_sub}</p>
				<p>{@html $delivery_provider.block_public_access_disabled_supported_desc} {@html $storage_provider.block_public_access_disabled_supported_desc}</p>
			{:else}
				<p>{@html $strings.block_public_access_disabled_sub}</p>
				<p>{@html $delivery_provider.block_public_access_disabled_unsupported_desc} {@html $storage_provider.block_public_access_disabled_unsupported_desc}</p>
			{/if}
		</PanelRow>
		{#if !$current_settings[ "block-public-access" ] && blockPublicAccess && $delivery_provider.block_public_access_supported}
			<div transition:slide|local>
				<PanelRow class="body flex-column toggle-reveal" footer>
					<Checkbox name="confirm-setup-bapa-oai" bind:checked={bapaSetupConfirmed} disabled={$needs_refresh || $settingsLocked}>{@html $delivery_provider.block_public_access_confirm_setup_prompt}</Checkbox>
				</PanelRow>
			</div>
		{/if}
	</Panel>

	<Panel
		class="toggle-header"
		heading={$strings.object_ownership_title}
		toggleName="object-ownership-enforced"
		bind:toggle={objectOwnershipEnforced}
		helpKey="object-ownership-enforced"
		multi
	>
		<PanelRow class="body flex-column">
			{#if initialSetup && $current_settings[ "object-ownership-enforced" ] && !$delivery_provider.object_ownership_supported}
				<p>{@html $strings.object_ownership_enforced_setup_sub}</p>
				<p>{@html $delivery_provider.object_ownership_enforced_unsupported_setup_desc} {@html $storage_provider.object_ownership_enforced_unsupported_setup_desc}</p>
			{:else if $current_settings[ "object-ownership-enforced" ] && $delivery_provider.object_ownership_supported}
				<p>{@html $strings.object_ownership_enforced_sub}</p>
				<p>{@html $delivery_provider.object_ownership_enforced_supported_desc} {@html $storage_provider.object_ownership_enforced_supported_desc}</p>
			{:else if $current_settings[ "object-ownership-enforced" ] && !$delivery_provider.object_ownership_supported}
				<p>{@html $strings.object_ownership_enforced_sub}</p>
				<p>{@html $delivery_provider.object_ownership_enforced_unsupported_desc} {@html $storage_provider.object_ownership_enforced_unsupported_desc}</p>
			{:else if !$current_settings[ "object-ownership-enforced" ] && $delivery_provider.object_ownership_supported}
				<p>{@html $strings.object_ownership_not_enforced_sub}</p>
				<p>{@html $delivery_provider.object_ownership_not_enforced_supported_desc} {@html $storage_provider.object_ownership_not_enforced_supported_desc}</p>
			{:else}
				<p>{@html $strings.object_ownership_not_enforced_sub}</p>
				<p>{@html $delivery_provider.object_ownership_not_enforced_unsupported_desc} {@html $storage_provider.object_ownership_not_enforced_unsupported_desc}</p>
			{/if}
		</PanelRow>
		{#if !$current_settings[ "object-ownership-enforced" ] && objectOwnershipEnforced && $delivery_provider.object_ownership_supported}
			<div transition:slide|local>
				<PanelRow class="body flex-column toggle-reveal">
					<Checkbox name="confirm-setup-ooe-oai" bind:checked={ooeSetupConfirmed} disabled={$needs_refresh || $settingsLocked}>{@html $delivery_provider.object_ownership_confirm_setup_prompt}</Checkbox>
				</PanelRow>
			</div>
		{/if}
	</Panel>

	<BackNextButtonsRow on:next={handleNext} {nextText} {nextDisabled}/>
</SubPage>
