<script>
	import {settings_notifications} from "../js/stores";
	import Notification from "./Notification.svelte";

	export let settingKey;

	/**
	 * Compares two notification objects to sort them into a preferred order.
	 *
	 * Order should be errors, then warnings and finally anything else alphabetically by type.
	 * As these (inline) notifications are typically displayed under a setting,
	 * this ensures the most important notifications are nearest the control.
	 *
	 * @param {Object} a
	 * @param {Object} b
	 *
	 * @return {number}
	 */
	function compareNotificationTypes( a, b ) {
		// Sort errors to the top.
		if ( a.type === "error" && b.type !== "error" ) {
			return -1;
		}
		if ( b.type === "error" && a.type !== "error" ) {
			return 1;
		}

		// Next sort warnings.
		if ( a.type === "warning" && b.type !== "warning" ) {
			return -1;
		}
		if ( b.type === "warning" && a.type !== "warning" ) {
			return 1;
		}

		// Anything else, just sort by type for stability.
		if ( a.type < b.type ) {
			return -1;
		}
		if ( b.type < a.type ) {
			return 1;
		}

		return 0;
	}
</script>

{#if $settings_notifications.has( settingKey )}
	{#each [...$settings_notifications.get( settingKey ).values()].sort( compareNotificationTypes ) as notification (notification)}
		<Notification {notification}>
			<p>{@html notification.message}</p>
		</Notification>
	{/each}
{/if}
