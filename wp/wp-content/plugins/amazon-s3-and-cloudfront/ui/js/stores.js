import {derived, writable, get, readable} from "svelte/store";
import {objectsDiffer} from "./objectsDiffer";

// Initial config store.
export const config = writable( {} );

// Whether settings are locked due to background activity such as upgrade.
export const settingsLocked = writable( false );

// Convenience readable store of server's settings, derived from config.
export const current_settings = derived( config, $config => $config.settings );

// Convenience readable store of defined settings keys, derived from config.
export const defined_settings = derived( config, $config => $config.defined_settings );

// Convenience readable store of translated strings, derived from config.
export const strings = derived( config, $config => $config.strings );

// Convenience readable store for nonce, derived from config.
export const nonce = derived( config, $config => $config.nonce );

// Convenience readable store of urls, derived from config.
export const urls = derived( config, $config => $config.urls );

// Convenience readable store of docs, derived from config.
export const docs = derived( config, $config => $config.docs );

// Convenience readable store of api endpoints, derived from config.
export const endpoints = derived( config, $config => $config.endpoints );

// Convenience readable store of diagnostics, derived from config.
export const diagnostics = derived( config, $config => $config.diagnostics );

// Convenience readable store of counts, derived from config.
export const counts = derived( config, $config => $config.counts );

// Convenience readable store of summary counts, derived from config.
export const summaryCounts = derived( config, $config => $config.summary_counts );

// Convenience readable store of offload remaining upsell, derived from config.
export const offloadRemainingUpsell = derived( config, $config => $config.offload_remaining_upsell );

// Convenience readable store of upgrades, derived from config.
export const upgrades = derived( config, $config => $config.upgrades );

// Convenience readable store of whether plugin is set up, derived from config.
export const is_plugin_setup = derived( config, $config => $config.is_plugin_setup );

// Convenience readable store of whether plugin is set up, including with credentials, derived from config.
export const is_plugin_setup_with_credentials = derived( config, $config => $config.is_plugin_setup_with_credentials );

// Convenience readable store of whether storage provider needs access credentials, derived from config.
export const needs_access_keys = derived( config, $config => $config.needs_access_keys );

// Convenience readable store of whether bucket is writable, derived from config.
export const bucket_writable = derived( config, $config => $config.bucket_writable );

// Convenience readable store of settings validation results, derived from config.
export const settings_validation = derived( config, $config => $config.settings_validation );

// Store of inline errors and warnings to be shown next to settings.
// Format is a map using settings key for keys, values are an array of objects that can be used to instantiate a notification.
export const settings_notifications = writable( new Map() );

// Store of validation errors for settings.
// Format is a map using settings key for keys, values are strings containing validation error.
export const validationErrors = writable( new Map() );

// Whether settings validations are being run.
export const revalidatingSettings = writable( false );

// Does the app need a page refresh to resolve conflicts?
export const needs_refresh = writable( false );

// Various stores may call the API, and the api object uses some stores.
// To avoid cyclic dependencies, we therefore co-locate the api object with the stores.
// We also need to add its functions much later so that JSHint does not complain about using the stores too early.
export const api = {};

/**
 * Creates store of settings.
 *
 * @return {Object}
 */
function createSettings() {
	const { subscribe, set, update } = writable( [] );

	return {
		subscribe,
		set,
		async save() {
			const json = await api.put( "settings", get( this ) );

			if ( json.hasOwnProperty( "saved" ) && true === json.saved ) {
				// Sync settings with what the server has.
				this.updateSettings( json );

				return json;
			}

			return { 'saved': false };
		},
		reset() {
			set( { ...get( current_settings ) } );
		},
		async fetch() {
			const json = await api.get( "settings", {} );
			this.updateSettings( json );
		},
		updateSettings( json ) {
			if (
				json.hasOwnProperty( "defined_settings" ) &&
				json.hasOwnProperty( "settings" ) &&
				json.hasOwnProperty( "storage_providers" ) &&
				json.hasOwnProperty( "delivery_providers" ) &&
				json.hasOwnProperty( "is_plugin_setup" ) &&
				json.hasOwnProperty( "is_plugin_setup_with_credentials" ) &&
				json.hasOwnProperty( "needs_access_keys" ) &&
				json.hasOwnProperty( "bucket_writable" ) &&
				json.hasOwnProperty( "urls" )
			) {
				// Update our understanding of what the server's settings are.
				config.update( $config => {
					return {
						...$config,
						defined_settings: json.defined_settings,
						settings: json.settings,
						storage_providers: json.storage_providers,
						delivery_providers: json.delivery_providers,
						is_plugin_setup: json.is_plugin_setup,
						is_plugin_setup_with_credentials: json.is_plugin_setup_with_credentials,
						needs_access_keys: json.needs_access_keys,
						bucket_writable: json.bucket_writable,
						urls: json.urls
					};
				} );
				// Update our local working copy of the settings.
				update( $settings => {
					return { ...json.settings };
				} );
			}
		}
	};
}

export const settings = createSettings();

// Have the settings been changed from current server side settings?
export const settings_changed = derived( [settings, current_settings], objectsDiffer );

// Convenience readable store of default storage provider, derived from config.
export const defaultStorageProvider = derived( config, $config => $config.default_storage_provider );

// Convenience readable store of available storage providers.
export const storage_providers = derived( [config, urls], ( [$config, $urls] ) => {
	for ( const key in $config.storage_providers ) {
		$config.storage_providers[ key ].icon = $urls.assets + "img/icon/provider/storage/" + $config.storage_providers[ key ].provider_key_name + ".svg";
		$config.storage_providers[ key ].link_icon = $urls.assets + "img/icon/provider/storage/" + $config.storage_providers[ key ].provider_key_name + "-link.svg";
		$config.storage_providers[ key ].round_icon = $urls.assets + "img/icon/provider/storage/" + $config.storage_providers[ key ].provider_key_name + "-round.svg";
	}

	return $config.storage_providers;
} );

// Convenience readable store of storage provider's details.
export const storage_provider = derived( [settings, storage_providers], ( [$settings, $storage_providers] ) => {
	if ( $settings.hasOwnProperty( "provider" ) && $storage_providers.hasOwnProperty( $settings.provider ) ) {
		return $storage_providers[ $settings.provider ];
	} else {
		return [];
	}
} );

// Convenience readable store of default delivery provider, derived from config.
export const defaultDeliveryProvider = derived( config, $config => $config.default_delivery_provider );

// Convenience readable store of available delivery providers.
export const delivery_providers = derived( [config, urls, storage_provider], ( [$config, $urls, $storage_provider] ) => {
	for ( const key in $config.delivery_providers ) {
		if ( "storage" === key ) {
			$config.delivery_providers[ key ].icon = $storage_provider.icon;
			$config.delivery_providers[ key ].round_icon = $storage_provider.round_icon;
			$config.delivery_providers[ key ].provider_service_quick_start_url = $storage_provider.provider_service_quick_start_url;
		} else {
			$config.delivery_providers[ key ].icon = $urls.assets + "img/icon/provider/delivery/" + $config.delivery_providers[ key ].provider_key_name + ".svg";
			$config.delivery_providers[ key ].round_icon = $urls.assets + "img/icon/provider/delivery/" + $config.delivery_providers[ key ].provider_key_name + "-round.svg";
		}
	}

	return $config.delivery_providers;
} );

// Convenience readable store of delivery provider's details.
export const delivery_provider = derived( [settings, delivery_providers, urls], ( [$settings, $delivery_providers, $urls] ) => {
	if ( $settings.hasOwnProperty( "delivery-provider" ) && $delivery_providers.hasOwnProperty( $settings[ "delivery-provider" ] ) ) {
		return $delivery_providers[ $settings[ "delivery-provider" ] ];
	} else {
		return [];
	}
} );

// Full name for current region.
export const region_name = derived( [settings, storage_provider, strings], ( [$settings, $storage_provider, $strings] ) => {
	if ( $settings.region && $storage_provider.regions && $storage_provider.regions.hasOwnProperty( $settings.region ) ) {
		return $storage_provider.regions[ $settings.region ];
	} else if ( $settings.region && $storage_provider.regions ) {
		// Region set but not available in list of regions.
		return $strings.unknown;
	} else if ( $storage_provider.default_region && $storage_provider.regions && $storage_provider.regions.hasOwnProperty( $storage_provider.default_region ) ) {
		// Region not set but default available.
		return $storage_provider.regions[ $storage_provider.default_region ];
	} else {
		// Possibly no default region or regions available.
		return $strings.unknown;
	}
} );

// Convenience readable store of whether Block All Public Access is enabled.
export const bapa = derived( [settings, storage_provider], ( [$settings, $storage_provider] ) => {
	return $storage_provider.block_public_access_supported && $settings.hasOwnProperty( "block-public-access" ) && $settings[ "block-public-access" ];
} );

// Convenience readable store of whether Object Ownership is enforced.
export const ooe = derived( [settings, storage_provider], ( [$settings, $storage_provider] ) => {
	return $storage_provider.object_ownership_supported && $settings.hasOwnProperty( "object-ownership-enforced" ) && $settings[ "object-ownership-enforced" ];
} );

/**
 * Creates a store of notifications.
 *
 * Example object in the array:
 * {
 * 	id: "error-message",
 * 	type: "error", // error | warning | success | primary (default)
 * 	dismissible: true,
 * 	flash: true, // Optional, means notification is context specific and will not persist on server, defaults to true.
 * 	inline: false, // Optional, unlikely to be true, included here for completeness.
 * 	only_show_on_tab: "media-library", // Optional, blank/missing means on all tabs.
 * 	heading: "Global Error: Something has gone terribly pear shaped.", // Optional.
 * 	message: "We're so sorry, but unfortunately we're going to have to delete the year 2020.", // Optional.
 * 	icon: "notification-error.svg", // Optional icon file name to be shown in front of heading.
 * 	plainHeading: false, // Optional boolean as to whether a <p> tag should be used instead of <h3> for heading content.
 * 	extra: "", // Optional extra content to be shown in paragraph below message.
 * 	links: [], // Optional list of links to be shown at bottom of notice.
 * },
 *
 * @return {Object}
 */
function createNotifications() {
	const { subscribe, set, update } = writable( [] );

	return {
		set,
		subscribe,
		add( notification ) {
			// There's a slight difference between our notification's formatting and what WP uses.
			if ( notification.hasOwnProperty( "type" ) && notification.type === "updated" ) {
				notification.type = "success";
			}
			if ( notification.hasOwnProperty( "type" ) && notification.type === "notice-warning" ) {
				notification.type = "warning";
			}
			if ( notification.hasOwnProperty( "type" ) && notification.type === "notice-info" ) {
				notification.type = "info";
			}
			if (
				notification.hasOwnProperty( "message" ) &&
				(!notification.hasOwnProperty( "heading" ) || notification.heading.trim().length === 0)
			) {
				notification.heading = notification.message;
				notification.plainHeading = true;
				delete notification.message;
			}
			if ( !notification.hasOwnProperty( "flash" ) ) {
				notification.flash = true;
			}

			// We need some sort of id for indexing and to ensure rendering is efficient.
			if ( !notification.hasOwnProperty( "id" ) ) {
				// Notifications are useless without at least a heading or message, so we can be sure at least one exists.
				const idHeading = notification.hasOwnProperty( "heading" ) ? notification.heading.trim() : "dynamic-heading";
				const idMessage = notification.hasOwnProperty( "message" ) ? notification.message.trim() : "dynamic-message";

				notification.id = btoa( idHeading + idMessage );
			}

			// So that rendering is efficient, but updates displayed notifications that re-use keys,
			// we create a render_key based on id and created_at as created_at is churned on re-use.
			const createdAt = notification.hasOwnProperty( "created_at" ) ? notification.created_at : 0;
			notification.render_key = notification.id + "-" + createdAt;

			update( $notifications => {
				// Maybe update a notification if id already exists.
				let index = -1;
				if ( notification.hasOwnProperty( "id" ) ) {
					index = $notifications.findIndex( _notification => _notification.id === notification.id );
				}

				if ( index >= 0 ) {
					// If the id exists but has been dismissed, add the replacement notification to the end of the array
					// if given notification is newer, otherwise skip it entirely.
					if ( $notifications[ index ].hasOwnProperty( "dismissed" ) ) {
						if ( $notifications[ index ].dismissed < notification.created_at ) {
							$notifications.push( notification );
							$notifications.splice( index, 1 );
						}
					} else {
						// Update existing.
						$notifications.splice( index, 1, notification );
					}
				} else {
					// Add new.
					$notifications.push( notification );
				}

				return $notifications.sort( this.sortCompare );
			} );
		},
		sortCompare( a, b ) {
			// Sort by created_at in case an existing notification was updated.
			if ( a.created_at < b.created_at ) {
				return -1;
			}

			if ( a.created_at > b.created_at ) {
				return 1;
			}

			return 0;
		},
		async dismiss( id ) {
			update( $notifications => {
				const index = $notifications.findIndex( notification => notification.id === id );

				// If the notification still exists, set a "dismissed" tombstone with the created_at value.
				// The cleanup will delete any notifications that have been dismissed and no longer exist
				// in the list of notifications retrieved from the server.
				// The created_at value ensures that if a notification is retrieved from the server that
				// has the same id but later created_at, then it can be added, otherwise it is skipped.
				if ( index >= 0 ) {
					if ( $notifications[ index ].hasOwnProperty( "created_at" ) ) {
						$notifications[ index ].dismissed = $notifications[ index ].created_at;
					} else {
						// Notification likely did not come from server, maybe a local "flash" notification.
						$notifications.splice( index, 1 );
					}
				}

				return $notifications;
			} );

			// Tell server to dismiss notification, still ok to try if flash notification, makes sure it is definitely removed.
			await api.delete( "notifications", { id: id, all_tabs: true } );
		},
		/**
		 * Delete removes a notification from the UI without telling the server.
		 */
		delete( id ) {
			update( $notifications => {
				const index = $notifications.findIndex( notification => notification.id === id );

				if ( index >= 0 ) {
					$notifications.splice( index, 1 );
				}

				return $notifications;
			} );
		},
		cleanup( latest ) {
			update( $notifications => {
				for ( const [index, notification] of $notifications.entries() ) {
					// Only clean up dismissed or server created notices that no longer exist.
					if ( notification.hasOwnProperty( "dismissed" ) || notification.hasOwnProperty( "created_at" ) ) {
						const latestIndex = latest.findIndex( _notification => _notification.id === notification.id );

						// If server doesn't know about the notification anymore, remove it.
						if ( latestIndex < 0 ) {
							$notifications.splice( index, 1 );
						}
					}
				}

				return $notifications;
			} );
		}
	};
}

export const notifications = createNotifications();

// Controller for periodic fetch of state info.
let stateFetchInterval;
let stateFetchIntervalStarted = false;
let stateFetchIntervalPaused = false;

// Store of functions to call before an update of state processes the result into config.
export const preStateUpdateCallbacks = writable( [] );

// Store of functions to call after an update of state processes the result into config.
export const postStateUpdateCallbacks = writable( [] );

/**
 * Store of functions to call when state info is updated, and actual API access methods.
 *
 * Functions are called after the returned state info has been used to update the config store.
 * Therefore, functions should only be added to the store if extra processing is required.
 * The functions should be asynchronous as they are part of the reactive chain and called with await.
 *
 * @return {Object}
 */
function createState() {
	const { subscribe, set, update } = writable( [] );

	return {
		subscribe,
		set,
		update,
		async fetch() {
			const json = await api.get( "state", {} );

			// Abort controller is still a bit hit or miss, so we'll go old skool.
			if ( stateFetchIntervalStarted && !stateFetchIntervalPaused ) {
				this.updateState( json );
			}
		},
		updateState( json ) {
			for ( const callable of get( preStateUpdateCallbacks ) ) {
				callable( json );
			}

			const dirty = get( settings_changed );
			const previous_settings = { ...get( current_settings ) }; // cloned

			config.update( $config => {
				return { ...$config, ...json };
			} );

			// If the settings weren't changed before, they shouldn't be now.
			if ( !dirty && get( settings_changed ) ) {
				settings.reset();
			}

			// If settings are in middle of being changed when changes come in
			// from server, reset to server version.
			if ( dirty && objectsDiffer( [previous_settings, get( current_settings )] ) ) {
				needs_refresh.update( $needs_refresh => true );
				settings.reset();
			}

			for ( const callable of get( postStateUpdateCallbacks ) ) {
				callable( json );
			}
		},
		async startPeriodicFetch() {
			stateFetchIntervalStarted = true;
			stateFetchIntervalPaused = false;

			await this.fetch();

			stateFetchInterval = setInterval( async () => {
				await this.fetch();
			}, 5000 );
		},
		stopPeriodicFetch() {
			stateFetchIntervalStarted = false;
			stateFetchIntervalPaused = false;

			clearInterval( stateFetchInterval );
		},
		pausePeriodicFetch() {
			if ( stateFetchIntervalStarted ) {
				stateFetchIntervalPaused = true;
				clearInterval( stateFetchInterval );
			}
		},
		async resumePeriodicFetch() {
			stateFetchIntervalPaused = false;

			if ( stateFetchIntervalStarted ) {
				await this.startPeriodicFetch();
			}
		}
	};
}

export const state = createState();

// API functions added here to avoid JSHint errors.
api.headers = () => {
	return {
		'Accept': 'application/json',
		'Content-Type': 'application/json',
		'X-WP-Nonce': get( nonce )
	};
};

api.url = ( endpoint ) => {
	return get( urls ).api + get( endpoints )[ endpoint ];
};

api.get = async ( endpoint, params ) => {
	let url = new URL( api.url( endpoint ) );

	const searchParams = new URLSearchParams( params );

	searchParams.forEach( function( value, name ) {
		url.searchParams.set( name, value );
	} );

	const response = await fetch( url.toString(), {
		method: 'GET',
		headers: api.headers()
	} );
	return response.json().then( json => {
		json = api.check_response( json );
		return json;
	} );
};

api.post = async ( endpoint, body ) => {
	const response = await fetch( api.url( endpoint ), {
		method: 'POST',
		headers: api.headers(),
		body: JSON.stringify( body )
	} );
	return response.json().then( json => {
		json = api.check_response( json );
		return json;
	} );
};

api.put = async ( endpoint, body ) => {
	const response = await fetch( api.url( endpoint ), {
		method: 'PUT',
		headers: api.headers(),
		body: JSON.stringify( body )
	} );
	return response.json().then( json => {
		json = api.check_response( json );
		return json;
	} );
};

api.delete = async ( endpoint, body ) => {
	const response = await fetch( api.url( endpoint ), {
		method: 'DELETE',
		headers: api.headers(),
		body: JSON.stringify( body )
	} );
	return response.json().then( json => {
		json = api.check_response( json );
		return json;
	} );
};

api.check_errors = ( json ) => {
	if ( json.code && json.message ) {
		notifications.add( {
			id: json.code,
			type: 'error',
			dismissible: true,
			heading: get( strings ).api_error_notice_heading,
			message: json.message
		} );

		// Just in case resultant json is expanded into a store.
		delete json.code;
		delete json.message;
	}

	return json;
};

api.check_notifications = ( json ) => {
	const _notifications = json.hasOwnProperty( "notifications" ) ? json.notifications : [];
	if ( _notifications ) {
		for ( const notification of _notifications ) {
			notifications.add( notification );
		}
	}
	notifications.cleanup( _notifications );

	// Just in case resultant json is expanded into a store.
	delete json.notifications;

	return json;
};

api.check_response = ( json ) => {
	json = api.check_notifications( json );
	json = api.check_errors( json );

	return json;
};
