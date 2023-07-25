<script>
	import {strings} from "../js/stores";
	import Page from "./Page.svelte";
	import Notifications from "./Notifications.svelte";
	import ToolsUpgrade from "./ToolsUpgrade.svelte";

	export let name = "tools";
</script>

<Page {name} on:routeEvent>
	<Notifications tab={name}/>
	<h2 class="page-title">{$strings.tools_title}</h2>
	<ToolsUpgrade/>
</Page>
