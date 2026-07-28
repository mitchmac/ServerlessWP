<script>
	import {strings, urls, docs} from "../js/stores";

	/**
	 * @typedef {Object} Props
	 * @property {string} [key]
	 * @property {any} [url]
	 * @property {string} [desc]
	 */

	/** @type {Props} */
	let {
		key = "",
		url = key && $docs.hasOwnProperty( key ) && $docs[ key ].hasOwnProperty( "url" ) ? $docs[ key ].url : "",
		desc = ""
	} = $props();

	// If desc supplied, use it, otherwise try and get via docs store or fall back to default help description.
	let docs_desc = $derived( key && $docs.hasOwnProperty( key ) && $docs[ key ].hasOwnProperty( "desc" ) ? $docs[ key ].desc : $strings.help_desc );
	let alt = $derived( desc.length ? desc : docs_desc );
	let title = $derived( alt );
</script>

{#if url}
	<a href={url} {title} class="help" target="_blank" data-setting-key={key}>
		<img class="icon help" src="{$urls.assets + 'img/icon/help.svg'}" {alt}/>
	</a>
{/if}
