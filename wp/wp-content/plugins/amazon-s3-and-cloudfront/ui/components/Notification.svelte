<script>
	import {notifications, strings, urls} from "../js/stores";
	import Button from "./Button.svelte";

	/**
	 * @typedef {Object} Props
	 * @property {any} [notification]
	 * @property {any} [unique_id]
	 * @property {any} [inline]
	 * @property {any} [wordpress]
	 * @property {any} [success]
	 * @property {any} [warning]
	 * @property {any} [error]
	 * @property {any} [heading]
	 * @property {any} [dismissible]
	 * @property {any} [icon]
	 * @property {any} [plainHeading]
	 * @property {any} [extra]
	 * @property {any} [links]
	 * @property {boolean} [expandable]
	 * @property {boolean} [expanded]
	 * @property {import("svelte").Snippet} [children]
	 * @property {import("svelte").Snippet} [details]
	 * @property {string} [class]
	 */

	/** @type {Props} */
	let {
		notification = $bindable( {} ),
		unique_id = notification.id ? notification.id : "",
		inline = notification.inline ? notification.inline : false,
		wordpress = notification.wordpress ? notification.wordpress : false,
		success = notification.type === "success",
		warning = notification.type === "warning",
		error = notification.type === "error",
		heading = notification.hasOwnProperty( "heading" ) && notification.heading.trim().length ? notification.heading.trim() : "",
		dismissible = notification.dismissible ? notification.dismissible : false,
		icon = notification.icon ? notification.icon : false,
		plainHeading = notification.plainHeading ? notification.plainHeading : false,
		extra = notification.extra ? notification.extra : "",
		links = notification.links ? notification.links : [],
		expandable = false,
		expanded = $bindable( false ),
		children,
		details,
		class: classes = ""
	} = $props();

	let info = $state( false );

	// It's possible to set type purely by component property,
	// but we need notification.type to be correct too.
	$effect( () => {
		if ( success ) {
			notification.type = "success";
		} else if ( warning ) {
			notification.type = "warning";
		} else if ( error ) {
			notification.type = "error";
		} else {
			info = true;
			notification.type = "info";
		}
	} );

	/**
	 * Returns the icon URL for the notification.
	 *
	 * @param {string|boolean} icon
	 * @param {string} notificationType
	 *
	 * @return {string}
	 */
	function getIconURL( icon, notificationType ) {
		if ( icon ) {
			return $urls.assets + "img/icon/" + icon;
		}

		return $urls.assets + "img/icon/notification-" + notificationType + ".svg";
	}

	let iconURL = $derived( getIconURL( icon, notification.type ) );

	// We need to change various properties and alignments if text is multiline.
	let iconHeight = $state( 0 );
	let bodyHeight = $state( 0 );

	let multiline = $derived( (iconHeight && bodyHeight) && bodyHeight > iconHeight );

	/**
	 * Builds a links row from an array of HTML links.
	 *
	 * @param {array} links
	 *
	 * @return {string}
	 */
	function getLinksHTML( links ) {
		if ( links.length ) {
			return links.join( " " );
		}

		return "";
	}

	let linksHTML = $derived( getLinksHTML( links ) );
</script>

<div
	class="notification {classes}"
	class:inline
	class:wordpress
	class:success
	class:warning
	class:error
	class:info
	class:multiline
	class:expandable
	class:expanded
>
	<div class="content">
		{#if iconURL}
			<div class="icon type" bind:clientHeight={iconHeight}>
				<img class="icon type" src={iconURL} alt="{notification.type} icon"/>
			</div>
		{/if}
		<div class="body" bind:clientHeight={bodyHeight}>
			{#if heading || dismissible || expandable}
				<div class="heading">
					{#if heading}
						{#if plainHeading}
							<p>{@html heading}</p>
						{:else}
							<h3>{@html heading}</h3>
						{/if}
					{/if}
					{#if dismissible && expandable}
						<button class="dismiss" onclick={(event) => {event.preventDefault(); notifications.dismiss(unique_id);}}>{$strings.dismiss_all}</button>
						<Button expandable {expanded} onclick={() => expanded = !expanded} title={expanded ? $strings.hide_details : $strings.show_details}></Button>
					{:else if expandable}
						<Button expandable {expanded} onclick={() => expanded = !expanded} title={expanded ? $strings.hide_details : $strings.show_details}></Button>
					{:else if dismissible}
						<button class="icon close" title={$strings["dismiss_notice"]} onclick={(event) => {event.preventDefault(); notifications.dismiss(unique_id);}}></button>
					{/if}
				</div>
			{/if}
			{@render children?.()}
			{#if extra}
				<p>{@html extra}</p>
			{/if}
			{#if linksHTML}
				<p class="links">{@html linksHTML}</p>
			{/if}
		</div>
	</div>
	{@render details?.()}
</div>
