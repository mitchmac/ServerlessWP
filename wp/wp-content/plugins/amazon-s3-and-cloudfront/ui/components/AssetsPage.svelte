<script>
	import {strings} from "../js/stores";
	import Page from "./Page.svelte";
	import AssetsUpgrade from "./AssetsUpgrade.svelte";

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {function} [onRouteEvent]
	 */

	/** @type {Props} */
	let { name = "assets", onRouteEvent } = $props();
</script>

<Page {name} {onRouteEvent}>
	<h2 class="page-title">{$strings.assets_title}</h2>
	<AssetsUpgrade/>
</Page>
