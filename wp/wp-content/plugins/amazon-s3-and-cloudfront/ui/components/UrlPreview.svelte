<script>
	import {scale} from "svelte/transition";
	import {api, settings, settings_changed, strings, urls} from "../js/stores";
	import Panel from "./Panel.svelte";
	import PanelRow from "./PanelRow.svelte";

	let parts = $urls.url_parts;

	/**
	 * When settings have changed, show their preview URL, otherwise show saved settings version.
	 *
	 * Note: This function **assigns** to the `example` and `parts` variables to defeat the reactive demons!
	 *
	 * @param {Object} urls
	 * @param {boolean} settingsChanged
	 * @param {Object} settings
	 *
	 * @returns boolean
	 */
	async function temporaryUrl( urls, settingsChanged, settings ) {
		if ( settingsChanged ) {
			const response = await api.post( "url-preview", {
				"settings": settings
			} );

			// Use temporary URLs if available.
			if ( response.hasOwnProperty( "url_parts" ) ) {
				parts = response.url_parts;

				return true;
			}
		}

		// Reset back to saved URLs.
		parts = urls.url_parts;

		return false;
	}

	$: isTemporaryUrl = temporaryUrl( $urls, $settings_changed, $settings );
</script>

{#if parts.length > 0}
	<Panel name="url-preview" heading={$strings.url_preview_title}>
		<PanelRow class="desc">
			<p>{$strings.url_preview_desc}</p>
		</PanelRow>
		<PanelRow class="body flex-row">
			<dl>
				{#each parts as part (part.title)}
					<div data-key={part.key} transition:scale|local>
						<dt>{part.title}</dt>
						<dd>{part.example}</dd>
					</div>
				{/each}
			</dl>
		</PanelRow>
	</Panel>
{/if}
