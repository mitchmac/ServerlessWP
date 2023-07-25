<script>
	import {notifications, strings, urls} from "../js/stores";
	import Button from "./Button.svelte";

	const classes = $$props.class ? $$props.class : "";

	export let notification = {};

	export let unique_id = notification.id ? notification.id : "";

	export let inline = notification.inline ? notification.inline : false;
	export let wordpress = notification.wordpress ? notification.wordpress : false;

	export let success = notification.type === "success";
	export let warning = notification.type === "warning";
	export let error = notification.type === "error";
	let info = false;

	// It's possible to set type purely by component property,
	// but we need notification.type to be correct too.
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

	export let heading = notification.hasOwnProperty( "heading" ) && notification.heading.trim().length ? notification.heading.trim() : "";
	export let dismissible = notification.dismissible ? notification.dismissible : false;
	export let icon = notification.icon ? notification.icon : false;
	export let plainHeading = notification.plainHeading ? notification.plainHeading : false;
	export let extra = notification.extra ? notification.extra : "";
	export let links = notification.links ? notification.links : [];
	export let expandable = false;
	export let expanded = false;

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

	$: iconURL = getIconURL( icon, notification.type );

	// We need to change various properties and alignments if text is multiline.
	let iconHeight = 0;
	let bodyHeight = 0;

	$: multiline = (iconHeight && bodyHeight) && bodyHeight > iconHeight;

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

	$: linksHTML = getLinksHTML( links );
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
						<button class="dismiss" on:click|preventDefault={notifications.dismiss(unique_id)}>{$strings.dismiss_all}</button>
						<Button expandable {expanded} on:click={() => expanded = !expanded} title={expanded ? $strings.hide_details : $strings.show_details}></Button>
					{:else if expandable}
						<Button expandable {expanded} on:click={() => expanded = !expanded} title={expanded ? $strings.hide_details : $strings.show_details}></Button>
					{:else if dismissible}
						<button class="icon close" title={$strings["dismiss_notice"]} on:click|preventDefault={() => notifications.dismiss(unique_id)}></button>
					{/if}
				</div>
			{/if}
			<slot/>
			{#if extra}
				<p>{@html extra}</p>
			{/if}
			{#if linksHTML}
				<p class="links">{@html linksHTML}</p>
			{/if}
		</div>
	</div>
	<slot name="details"/>
</div>
