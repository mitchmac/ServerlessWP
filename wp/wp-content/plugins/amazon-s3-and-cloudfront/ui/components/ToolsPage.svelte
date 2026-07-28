<script>
	import {strings} from "../js/stores";
	import Page from "./Page.svelte";
	import Notifications from "./Notifications.svelte";
	import ToolsUpgrade from "./ToolsUpgrade.svelte";

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {function} [onRouteEvent]
	 */

	/** @type {Props} */
	let { name = "tools", onRouteEvent } = $props();
</script>

<Page {name} {onRouteEvent}>
	<Notifications tab={name}/>
	<h2 class="page-title">{$strings.tools_title}</h2>
	<ToolsUpgrade/>
</Page>
