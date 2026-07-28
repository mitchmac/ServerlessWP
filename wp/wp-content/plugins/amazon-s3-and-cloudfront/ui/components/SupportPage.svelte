<script>
	import {onMount} from "svelte";
	import {api, config, diagnostics, strings, urls} from "../js/stores";
	import Page from "./Page.svelte";
	import Notifications from "./Notifications.svelte";

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {any} [title]
	 * @property {import("svelte").Snippet} [header]
	 * @property {import("svelte").Snippet} [content]
	 * @property {import("svelte").Snippet} [footer]
	 * @property {function} [onRouteEvent]
	 */

	/** @type {Props} */
	let {
		name = "support",
		title = $strings.support_tab_title,
		header,
		content,
		footer,
		onRouteEvent
	} = $props();

	onMount( async () => {
		const json = await api.get( "diagnostics", {} );

		if ( json.hasOwnProperty( "diagnostics" ) ) {
			$config.diagnostics = json.diagnostics;
		}
	} );
</script>

<Page {name} {onRouteEvent}>
	<Notifications tab={name}/>
	{#if title}
		<h2 class="page-title">{title}</h2>
	{/if}
	<div class="support-page wrapper">

		{@render header?.()}

		<div class="columns">
			<div class="support-form">
				{#if content}
					{@render content()}
				{:else}
					<div class="lite-support">
						<p>{@html $strings.no_support}</p>
						<p>{@html $strings.community_support}</p>
						<p>{@html $strings.upgrade_for_support}</p>
						<p>{@html $strings.report_a_bug}</p>
					</div>
				{/if}

				<div class="diagnostic-info">
					<hr>
					<h2 class="page-title">{$strings.diagnostic_info_title}</h2>
					<pre>{$diagnostics}</pre>
					<a href={$urls.download_diagnostics} class="button btn-md btn-outline">{$strings.download_diagnostics}</a>
				</div>
			</div>

			{@render footer?.()}
		</div>
	</div>
</Page>
