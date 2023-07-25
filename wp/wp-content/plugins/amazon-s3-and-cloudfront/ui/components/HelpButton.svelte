<script>
	import {strings, urls, docs} from "../js/stores";

	export let key = "";
	export let url = key && $docs.hasOwnProperty( key ) && $docs[ key ].hasOwnProperty( "url" ) ? $docs[ key ].url : "";
	export let desc = "";

	// If desc supplied, use it, otherwise try and get via docs store or fall back to default help description.
	let alt = desc.length ? desc : (key && $docs.hasOwnProperty( key ) && $docs[ key ].hasOwnProperty( "desc" ) ? $docs[ key ].desc : $strings.help_desc);
	let title = alt;
</script>

{#if url}
	<a href={url} {title} class="help" target="_blank" data-setting-key={key}>
		<img class="icon help" src="{$urls.assets + 'img/icon/help.svg'}" {alt}/>
	</a>
{/if}
