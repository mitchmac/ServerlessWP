<script>
	import {onMount, setContext} from "svelte";
	import {
		config,
		current_settings,
		defaultStorageProvider,
		needs_access_keys,
		needs_refresh,
		notifications,
		postStateUpdateCallbacks,
		settings,
		settings_changed,
		settings_notifications,
		settingsLocked,
		strings
	} from "../js/stores";
	import {pages} from "../js/routes";
	import {defaultPages} from "../js/defaultPages";
	import {addPages} from "./pages";
	import {settingsNotifications} from "../js/settingsNotifications";
	import Settings from "../components/Settings.svelte";
	import Header from "./Header.svelte";
	import Pages from "../components/Pages.svelte";
	import Sidebar from "./Sidebar.svelte";

	export let init = {};

	// During initialization set config store to passed in values to avoid undefined values in components during mount.
	// This saves having to do a lot of checking of values before use.
	config.set( init );
	pages.set( defaultPages );

	// Add Lite specific pages.
	addPages();

	setContext( 'sidebar', Sidebar );

	/**
	 * Handles state update event's changes to config.
	 *
	 * @param {Object} config
	 *
	 * @return {Promise<void>}
	 */
	async function handleStateUpdate( config ) {
		if ( config.upgrades.is_upgrading ) {
			$settingsLocked = true;

			const notification = {
				id: "as3cf-media-settings-locked",
				type: "warning",
				dismissible: false,
				only_show_on_tab: "media",
				heading: config.upgrades.locked_notifications[ config.upgrades.running_upgrade ],
				icon: "notification-locked.svg",
				plainHeading: true
			};
			notifications.add( notification );

			if ( $settings_changed ) {
				settings.reset();
			}
		} else if ( $needs_refresh ) {
			$settingsLocked = true;

			const notification = {
				id: "as3cf-media-settings-locked",
				type: "warning",
				dismissible: false,
				only_show_on_tab: "media",
				heading: $strings.needs_refresh,
				icon: "notification-locked.svg",
				plainHeading: true
			};
			notifications.add( notification );
		} else {
			$settingsLocked = false;

			notifications.delete( "as3cf-media-settings-locked" );
		}

		// Show a persistent error notice if bucket can't be accessed.
		if ( $needs_access_keys && ($settings.provider !== $defaultStorageProvider || $settings.bucket.length !== 0) ) {
			const notification = {
				id: "as3cf-needs-access-keys",
				type: "error",
				dismissible: false,
				only_show_on_tab: "media",
				hide_on_parent: true,
				heading: $strings.needs_access_keys,
				plainHeading: true
			};
			notifications.add( notification );
		} else {
			notifications.delete( "as3cf-needs-access-keys" );
		}
	}

	// Catch changes to needing access credentials as soon as possible.
	$: if ( $needs_access_keys ) {
		handleStateUpdate( $config );
	}

	onMount( () => {
		// Make sure state dependent data is up-to-date.
		handleStateUpdate( $config );

		// When state info is fetched we need some extra processing of the data.
		postStateUpdateCallbacks.update( _callables => {
			return [..._callables, handleStateUpdate];
		} );
	} );

	// Make sure all inline notifications are in place.
	$: settings_notifications.update( ( notices ) => settingsNotifications.process( notices, $settings, $current_settings, $strings ) );
</script>

<Settings header={Header}>
	<Pages class="lite-wrapper"/>
</Settings>
