import {mount} from "svelte";
import Settings from "./Settings.svelte";

const app = mount(
	Settings,
	{
		target: document.getElementById( "as3cf-settings" ),
		props: {
			init: as3cf_settings
		}
	}
);
