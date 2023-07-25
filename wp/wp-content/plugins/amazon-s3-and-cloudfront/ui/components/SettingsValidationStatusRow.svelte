<script>
	import {
		settings_validation,
		urls
	} from "../js/stores";
	import CheckAgain from "./CheckAgain.svelte";

	export let section = "";

	$: success = $settings_validation[ section ].type === "success";
	$: warning = $settings_validation[ section ].type === "warning";
	$: error = $settings_validation[ section ].type === "error";
	$: info = $settings_validation[ section ].type === "info";
	$: type = $settings_validation[ section ].type;

	$: message = '<p>' + $settings_validation[ section ].message + '</p>';
	$: iconURL = $urls.assets + "img/icon/notification-" + $settings_validation[ section ].type + ".svg";
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
