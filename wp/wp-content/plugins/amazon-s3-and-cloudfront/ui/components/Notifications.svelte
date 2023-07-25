<script>
	import {notifications} from "../js/stores";
	import Notification from "./Notification.svelte";

	export let component = Notification;
	export let tab = "";
	export let tabParent = "";

	/**
	 * Render the notification or not?
	 */
	function renderNotification( notification ) {
		let not_dismissed = !notification.dismissed;
		let valid_parent_tab = notification.only_show_on_tab === tab && notification.hide_on_parent !== true;
		let valid_sub_tab = notification.only_show_on_tab === tabParent;
		let show_on_all_tabs = !notification.only_show_on_tab;

		return not_dismissed && (valid_parent_tab || valid_sub_tab || show_on_all_tabs);
	}
</script>

{#if $notifications.length && Object.values( $notifications ).filter( notification => renderNotification( notification ) ).length}
	<div id="notifications" class="notifications wrapper">
		{#each $notifications as notification (notification.render_key)}
			{#if renderNotification( notification )}
				<svelte:component this={component} notification={notification}>
					{#if notification.message}
						<p>{@html notification.message}</p>
					{/if}
				</svelte:component>
			{/if}
		{/each}
	</div>
{/if}
