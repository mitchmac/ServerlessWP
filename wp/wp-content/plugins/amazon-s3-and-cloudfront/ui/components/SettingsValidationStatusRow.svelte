<script>
	import {
		settings_validation,
		urls
	} from "../js/stores";
	import CheckAgain from "./CheckAgain.svelte";

	/**
	 * @typedef {Object} Props
	 * @property {string} [section]
	 */

	/** @type {Props} */
	let { section = "" } = $props();

	let success = $derived( $settings_validation[ section ].type === "success" );
	let warning = $derived( $settings_validation[ section ].type === "warning" );
	let error = $derived( $settings_validation[ section ].type === "error" );
	let info = $derived( $settings_validation[ section ].type === "info" );
	let type = $derived( $settings_validation[ section ].type );

	let message = $derived( "<p>" + $settings_validation[ section ].message + "</p>" );
	let iconURL = $derived( $urls.assets + "img/icon/notification-" + $settings_validation[ section ].type + ".svg" );
</script>

<div
	class="notification in-panel multiline {section}"
	class:success
	class:warning
	class:error
	class:info
>
	<div class="content in-panel">
		<div class="icon type in-panel">
			<img class="icon type" src={iconURL} alt="{type} icon"/>
		</div>

		<div class="body">
			{@html message}
		</div>

		<CheckAgain section={section}/>
	</div>
</div>
