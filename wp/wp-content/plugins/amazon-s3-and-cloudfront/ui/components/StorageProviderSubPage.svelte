<script>
	import {getContext, hasContext} from "svelte";
	import {writable} from "svelte/store";
	import {
		settings,
		defined_settings,
		strings,
		storage_providers,
		storage_provider,
		counts,
		current_settings,
		needs_refresh,
		revalidatingSettings,
		appState
	} from "../js/stores";
	import {
		scrollNotificationsIntoView
	} from "../js/scrollNotificationsIntoView";
	import {needsRefresh} from "../js/needsRefresh";
	import SubPage from "./SubPage.svelte";
	import Panel from "./Panel.svelte";
	import PanelRow from "./PanelRow.svelte";
	import TabButton from "./TabButton.svelte";
	import RadioButton from "./RadioButton.svelte";
	import AccessKeysDefine from "./AccessKeysDefine.svelte";
	import BackNextButtonsRow from "./BackNextButtonsRow.svelte";
	import KeyFileDefine from "./KeyFileDefine.svelte";
	import UseServerRolesDefine from "./UseServerRolesDefine.svelte";
	import AccessKeysEntry from "./AccessKeysEntry.svelte";
	import KeyFileEntry from "./KeyFileEntry.svelte";
	import Notification from "./Notification.svelte";

	/**
	 * @typedef {Object} Props
	 * @property {function} [onRouteEvent]
	 */

	/** @type {Props} */
	let { onRouteEvent } = $props();

	// Parent page may want to be locked.
	let settingsLocked = $state( writable( false ) );

	if ( hasContext( "settingsLocked" ) ) {
		settingsLocked = getContext( "settingsLocked" );
	}

	// Need to be careful about throwing unneeded warnings.
	let initialSettings = $state( $current_settings );

	if ( hasContext( "initialSettings" ) ) {
		initialSettings = getContext( "initialSettings" );
	}

	// As this page does not directly alter the settings store until done,
	// we need to keep track of any changes made elsewhere and prompt
	// the user to refresh the page.
	let saving = $state( false );
	const previousSettings = { ...$current_settings };
	const previousDefines = { ...$defined_settings };

	$effect.pre( () => {
		$needs_refresh = $needs_refresh || needsRefresh( saving, previousSettings, $current_settings, previousDefines, $defined_settings );
	} );

	/*
	 * 1. Select Storage Provider
	 */

	let storageProvider = $state( { ...$storage_provider } );

	let defined = $derived( $defined_settings.includes( "provider" ) );
	let disabled = $derived( defined || $needs_refresh || $settingsLocked );

	/**
	 * Handles picking different storage provider.
	 *
	 * @param {Object} provider
	 */
	function handleChooseProvider( provider ) {
		if ( disabled ) {
			return;
		}

		storageProvider = provider;

		// Now make sure authMethod is valid for chosen storage provider.
		authMethod = getAuthMethod( storageProvider, authMethod );
	}

	let changedWithOffloaded = $derived( initialSettings.provider !== storageProvider.provider_key_name && $counts.offloaded > 0 );

	/*
	 * 2. Select Authentication method
	 */

	let accessKeyId = $state( $settings[ "access-key-id" ] );
	let secretAccessKey = $state( $settings[ "secret-access-key" ] );
	let keyFile = $state( $settings[ "key-file" ] ? JSON.stringify( $settings[ "key-file" ] ) : "" );

	/**
	 * For the given current storage provider, determine the authentication method or fallback to currently selected.
	 * It's possible that the storage provider can be freely changed but the
	 * authentication method is defined (fixed) differently for each, or freely changeable too.
	 * The order of evaluation in this function is important and mirrors the server side evaluation order.
	 *
	 * @param {provider} provider
	 * @param {string} current auth method, one of "define", "server-role" or "database" if set.
	 *
	 * @return {string}
	 */
	function getAuthMethod( provider, current = "" ) {
		if ( provider.use_access_keys_allowed && provider.used_access_keys_constants.length ) {
			return "define";
		}

		if ( provider.use_key_file_allowed && provider.used_key_file_path_constants.length ) {
			return "define";
		}

		if ( provider.use_server_roles_allowed && provider.used_server_roles_constants.length ) {
			return "server-role";
		}

		if ( current === "server-role" && !provider.use_server_roles_allowed ) {
			return "define";
		}

		if ( current.length === 0 ) {
			if ( provider.use_access_keys_allowed && (accessKeyId || secretAccessKey) ) {
				return "database";
			}

			if ( provider.use_key_file_allowed && keyFile ) {
				return "database";
			}

			if ( provider.use_server_roles_allowed && $settings[ "use-server-roles" ] ) {
				return "server-role";
			}

			// Default to most secure option.
			return "define";
		}

		return current;
	}

	// Set initial auth method.
	// svelte-ignore state_referenced_locally
	let authMethod = $state( getAuthMethod( storageProvider ) );

	// If auth method is not allowed to be database, then either define or server-role is being forced, likely by a define.
	let authDefined = $derived( "database" !== getAuthMethod( storageProvider, "database" ) );
	let authDisabled = $derived( authDefined || $needs_refresh || $settingsLocked );

	/*
	 * 3. Save Authentication Credentials
	 */

	/**
	 * Returns a title string to be used for the credentials panel as appropriate for the auth method.
	 *
	 * @param {string} method
	 * @return {*}
	 */
	function getCredentialsTitle( method ) {
		return $strings.auth_method_title[ method ];
	}

	let saveCredentialsTitle = $derived( getCredentialsTitle( authMethod ) );

	/*
	 * Do Something!
	 */

	/**
	 * Handles a Next button click.
	 *
	 * @return {Promise<void>}
	 */
	async function handleNext() {
		saving = true;
		appState.pausePeriodicFetch();

		$settings.provider = storageProvider.provider_key_name;
		$settings[ "access-key-id" ] = accessKeyId;
		$settings[ "secret-access-key" ] = secretAccessKey;
		$settings[ "use-server-roles" ] = authMethod === "server-role";
		$settings[ "key-file" ] = keyFile;
		const result = await settings.save();

		// If something went wrong, don't move onto next step.
		if ( !result.hasOwnProperty( "saved" ) || !result.saved ) {
			settings.reset();
			saving = false;
			await appState.resumePeriodicFetch();

			scrollNotificationsIntoView();

			return;
		}

		$revalidatingSettings = true;
		const statePromise = appState.resumePeriodicFetch();

		onRouteEvent( { event: "settings.save", data: result } );

		// Just make sure periodic state fetch promise is done with,
		// even though we don't really care about it.
		await statePromise;
		$revalidatingSettings = false;
	}
</script>

<SubPage name="storage-provider-settings" route="/storage/provider">
	{#if changedWithOffloaded}
		<Notification inline warning heading={storageProvider.media_already_offloaded_warning.heading}>
			<p>{@html storageProvider.media_already_offloaded_warning.message}</p>
		</Notification>
	{/if}

	<Panel heading={$strings.select_storage_provider_title} defined={defined} multi>
		<PanelRow class="body flex-row tab-buttons">
			{#each Object.values( $storage_providers ) as provider}
				{#if !provider.is_deprecated || provider.provider_key_name === storageProvider.provider_key_name}
					<TabButton
						active={provider.provider_key_name === storageProvider.provider_key_name}
						{disabled}
						icon={provider.icon}
						iconDesc={provider.icon_desc}
						text={provider.provider_service_name}
						onclick={(event) => {event.preventDefault(); handleChooseProvider( provider );}}
					/>
				{/if}
			{/each}

			<Notification class="notice-qsg">
				<p>{@html storageProvider.get_access_keys_help}</p>
			</Notification>
		</PanelRow>
	</Panel>

	<Panel heading={$strings.select_auth_method_title} defined={authDefined} multi>
		<PanelRow class="body flex-column">
			<!-- define -->
			{#if storageProvider.use_access_keys_allowed}
				<RadioButton bind:selected={authMethod} disabled={authDisabled} value="define" desc={storageProvider.defined_auth_desc}>
					{$strings.define_access_keys}
				</RadioButton>
			{:else if storageProvider.use_key_file_allowed}
				<RadioButton bind:selected={authMethod} disabled={authDisabled} value="define" desc={storageProvider.defined_auth_desc}>
					{$strings.define_key_file_path}
				</RadioButton>
			{/if}

			<!-- server-role -->
			{#if storageProvider.use_server_roles_allowed}
				<RadioButton bind:selected={authMethod} disabled={authDisabled} value="server-role" desc={storageProvider.defined_auth_desc}>
					{storageProvider.use_server_roles_title}
				</RadioButton>
			{/if}

			<!-- database -->
			{#if storageProvider.use_access_keys_allowed}
				<RadioButton bind:selected={authMethod} disabled={authDisabled} value="database">
					{$strings.store_access_keys_in_db}
				</RadioButton>
			{:else if storageProvider.use_key_file_allowed}
				<RadioButton bind:selected={authMethod} disabled={authDisabled} value="database">
					{$strings.store_key_file_in_db}
				</RadioButton>
			{/if}
		</PanelRow>
	</Panel>

	{#if !authDefined}
		<Panel heading={saveCredentialsTitle} multi>
			<PanelRow class="body flex-column access-keys">
				{#if authMethod === "define" && storageProvider.use_access_keys_allowed}
					<AccessKeysDefine provider={storageProvider}/>
				{:else if authMethod === "define" && storageProvider.use_key_file_allowed}
					<KeyFileDefine provider={storageProvider}/>
				{:else if authMethod === "server-role" && storageProvider.use_server_roles_allowed}
					<UseServerRolesDefine provider={storageProvider}/>
				{:else if authMethod === "database" && storageProvider.use_access_keys_allowed}
					<AccessKeysEntry
						provider={storageProvider}
						bind:accessKeyId
						bind:secretAccessKey
						disabled={authDisabled}
					/>
				{:else if authMethod === "database" && storageProvider.use_key_file_allowed}
					<KeyFileEntry provider={storageProvider} bind:value={keyFile}/>
				{/if}
			</PanelRow>
		</Panel>
	{/if}

	<BackNextButtonsRow onNext={handleNext} nextDisabled={$needs_refresh || $settingsLocked} nextText={$strings.save_and_continue}/>
</SubPage>
