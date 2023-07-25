<script>
	import {onMount} from "svelte";
	import {api, config, diagnostics, strings, urls} from "../js/stores";
	import Page from "./Page.svelte";
	import Notifications from "./Notifications.svelte";

	export let name = "support";
	export let title = $strings.support_tab_title;

	onMount( async () => {
		const json = await api.get( "diagnostics", {} );

		if ( json.hasOwnProperty( "diagnostics" ) ) {
			$config.diagnostics = json.diagnostics;
		}
	} );
</script>

<Page {name} on:routeEvent>
	<Notifications tab={name}/>
	{#if title}
		<h2 class="page-title">{title}</h2>
	{/if}
	<div class="support-page wrapper">

		<slot name="header"/>

		<div class="columns">
			<div class="support-form">
				<slot name="content">
					<div class="lite-support">
						<p>{@html $strings.no_support}</p>
						<p>{@html $strings.community_support}</p>
						<p>{@html $strings.upgrade_for_support}</p>
						<p>{@html $strings.report_a_bug}</p>
					</div>
				</slot>

				<div class="diagnostic-info">
					<hr>
					<h2 class="page-title">{$strings.diagnostic_info_title}</h2>
					<pre>{$diagnostics}</pre>
					<a href={$urls.download_diagnostics} class="button btn-md btn-outline">{$strings.download_diagnostics}</a>
				</div>
			</div>

			<slot name="footer"/>
		</div>
	</div>
</Page>
