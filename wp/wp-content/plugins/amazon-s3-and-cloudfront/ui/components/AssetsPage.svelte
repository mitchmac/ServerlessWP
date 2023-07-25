<script>
	import {strings} from "../js/stores";
	import Page from "./Page.svelte";
	import AssetsUpgrade from "./AssetsUpgrade.svelte";

	export let name = "assets";
</script>

<Page {name} on:routeEvent>
	<h2 class="page-title">{$strings.assets_title}</h2>
	<AssetsUpgrade/>
</Page>
