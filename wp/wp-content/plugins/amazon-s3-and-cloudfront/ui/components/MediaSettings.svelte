<script>
	import StorageSettingsSubPage from "./StorageSettingsSubPage.svelte";
	import DeliverySettingsSubPage from "./DeliverySettingsSubPage.svelte";

	export let params = {}; // Required for regex routes.
	const _params = params; // Stops compiler warning about unused params export;
</script>

<StorageSettingsSubPage/>
<DeliverySettingsSubPage/>