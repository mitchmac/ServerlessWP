import {get} from "svelte/store";
import {strings} from "../js/stores";
import {pages} from "../js/routes";
import AssetsPage from "../components/AssetsPage.svelte";
import ToolsPage from "../components/ToolsPage.svelte";
import SupportPage from "../components/SupportPage.svelte";

/**
 * Adds Lite specific pages.
 */
export function addPages() {
	pages.add(
		{
			position: 10,
			name: "assets",
			title: () => get( strings ).assets_tab_title,
			nav: true,
			route: "/assets",
			component: AssetsPage
		}
	);
	pages.add(
		{
			position: 20,
			name: "tools",
			title: () => get( strings ).tools_tab_title,
			nav: true,
			route: "/tools",
			component: ToolsPage
		}
	);
	pages.add(
		{
			position: 100,
			name: "support",
			title: () => get( strings ).support_tab_title,
			nav: true,
			route: "/support",
			component: SupportPage
		}
	);
}