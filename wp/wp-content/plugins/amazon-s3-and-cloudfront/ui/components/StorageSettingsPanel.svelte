<script>
	import {settings, strings} from "../js/stores";
	import Panel from "./Panel.svelte";
	import StorageSettingsHeadingRow from "./StorageSettingsHeadingRow.svelte";
	import SettingsValidationStatusRow
		from "./SettingsValidationStatusRow.svelte";
	import SettingsPanelOption from "./SettingsPanelOption.svelte";
</script>

<Panel name="settings" heading={$strings.storage_settings_title} helpKey="storage-provider">
	<StorageSettingsHeadingRow/>
	<SettingsValidationStatusRow section="storage"/>
	<SettingsPanelOption
		heading={$strings.copy_files_to_bucket}
		description={$strings.copy_files_to_bucket_desc}
		toggleName="copy-to-s3"
		bind:toggle={$settings["copy-to-s3"]}
	/>
	<SettingsPanelOption
		heading={$strings.remove_local_file}
		description={$strings.remove_local_file_desc}
		toggleName="remove-local-file"
		bind:toggle={$settings["remove-local-file"]}
	>
	</SettingsPanelOption>
	<SettingsPanelOption
		heading={$strings.path}
		description={$strings.path_desc}
		toggleName="enable-object-prefix"
		bind:toggle={$settings["enable-object-prefix"]}
		textName="object-prefix"
		bind:text={$settings["object-prefix"]}
	/>
	<SettingsPanelOption
		heading={$strings.year_month}
		description={$strings.year_month_desc}
		toggleName="use-yearmonth-folders"
		bind:toggle={$settings["use-yearmonth-folders"]}
	>
	</SettingsPanelOption>
	<SettingsPanelOption
		heading={$strings.object_versioning}
		description={$strings.object_versioning_desc}
		toggleName="object-versioning"
		bind:toggle={$settings["object-versioning"]}
	>
	</SettingsPanelOption>
</Panel>
