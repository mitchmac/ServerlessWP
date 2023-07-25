import {get} from "svelte/store";
import {location} from "svelte-spa-router";
import {
	strings,
	storage_provider,
	is_plugin_setup_with_credentials,
	is_plugin_setup,
	needs_access_keys,
	delivery_provider
} from "./stores";

// Components used for default pages.
import MediaPage from "../components/MediaPage.svelte";
import StoragePage from "../components/StoragePage.svelte";
import StorageProviderSubPage
	from "../components/StorageProviderSubPage.svelte";
import BucketSettingsSubPage from "../components/BucketSettingsSubPage.svelte";
import SecuritySubPage from "../components/SecuritySubPage.svelte";
import DeliveryPage from "../components/DeliveryPage.svelte";

// Default pages, having a title means inclusion in main tabs.
// NOTE: get() only resolves after initialization, hence arrow functions for getting titles.
export const defaultPages = [
	{
		position: 0,
		name: "media-library",
		title: () => get( strings ).media_tab_title,
		nav: true,
		route: "/",
		routeMatcher: /^\/(media\/.*)*$/,
		component: MediaPage,
		default: true
	},
	{
		position: 200,
		name: "storage",
		route: "/storage/*",
		component: StoragePage
	},
	{
		position: 210,
		name: "storage-provider",
		title: () => get( strings ).storage_provider_tab_title,
		subNav: true,
		route: "/storage/provider",
		component: StorageProviderSubPage,
		default: true,
		events: {
			"page.initial.settings": ( data ) => {
				// We need Storage Provider credentials for some pages to be useful.
				if ( data.hasOwnProperty( "location" ) && get( needs_access_keys ) && !get( is_plugin_setup ) ) {
					for ( const prefix of ["/storage", "/media", "/delivery"] ) {
						if ( data.location.startsWith( prefix ) ) {
							return true;
						}
					}

					return data.location === "/";
				}

				return false;
			}
		}
	},
	{
		position: 220,
		name: "bucket",
		title: () => get( strings ).bucket_tab_title,
		subNav: true,
		route: "/storage/bucket",
		component: BucketSettingsSubPage,
		enabled: () => {
			return !get( needs_access_keys );
		},
		events: {
			"page.initial.settings": ( data ) => {
				// We need a bucket and region to have been verified before some pages are useful.
				if ( data.hasOwnProperty( "location" ) && !get( needs_access_keys ) && !get( is_plugin_setup ) ) {
					for ( const prefix of ["/storage", "/media", "/delivery"] ) {
						if ( data.location.startsWith( prefix ) ) {
							return true;
						}
					}

					return data.location === "/";
				}

				return false;
			},
			"settings.save": ( data ) => {
				// If currently in /storage/provider route, bucket is always next, assuming storage provider set up correctly.
				return get( location ) === "/storage/provider" && !get( needs_access_keys );
			}
		}
	},
	{
		position: 230,
		name: "security",
		title: () => get( strings ).security_tab_title,
		subNav: true,
		route: "/storage/security",
		component: SecuritySubPage,
		enabled: () => {
			return get( is_plugin_setup_with_credentials ) && !get( storage_provider ).requires_acls;
		},
		events: {
			"settings.save": ( data ) => {
				// If currently in /storage/bucket route,
				// and storage provider does not require ACLs,
				// and bucket wasn't just created during initial set up
				// with delivery provider compatible access control,
				// then security is next.
				if (
					get( location ) === "/storage/bucket" &&
					get( is_plugin_setup_with_credentials ) &&
					!get( storage_provider ).requires_acls &&
					(
						!data.hasOwnProperty( "bucketSource" ) || // unexpected data issue
						data.bucketSource !== "new" || // bucket not created
						!data.hasOwnProperty( "initialSettings" ) || // unexpected data issue
						!data.initialSettings.hasOwnProperty( "bucket" ) || // unexpected data issue
						data.initialSettings.bucket.length > 0 || // bucket previously set
						!data.hasOwnProperty( "settings" ) || // unexpected data issue
						!data.settings.hasOwnProperty( "use-bucket-acls" ) || // unexpected data issue
						(
							!data.settings[ "use-bucket-acls" ] && // bucket not using ACLs ...
							get( delivery_provider ).requires_acls // ... but delivery provider needs ACLs
						)
					)
				) {
					return true;
				}

				return false;
			}
		}
	},
	{
		position: 300,
		name: "delivery",
		route: "/delivery/*",
		component: DeliveryPage
	},
];
