<script>
	import {delivery_provider, settings, strings} from "../js/stores";
	import Panel from "./Panel.svelte";
	import DeliverySettingsHeadingRow
		from "./DeliverySettingsHeadingRow.svelte";
	import SettingsValidationStatusRow from "./SettingsValidationStatusRow.svelte";
	import SettingsPanelOption from "./SettingsPanelOption.svelte";

	/**
	 * Potentially returns a reason that the provided domain name is invalid.
	 *
	 * @param {string} domain
	 *
	 * @return {string}
	 */
	function domainValidator( domain ) {
		const domainPattern = /[^a-z0-9.-]/;

		let message = "";

		if ( domain.trim().length === 0 ) {
			message = $strings.domain_blank;
		} else if ( true === domainPattern.test( domain ) ) {
			message = $strings.domain_invalid_content;
		} else if ( domain.length < 3 ) {
			message = $strings.domain_too_short;
		}

		return message;
	}
</script>

<Panel name="settings" heading={$strings.delivery_settings_title} helpKey="delivery-provider">
	<DeliverySettingsHeadingRow/>
	<SettingsValidationStatusRow section="delivery"/>
	<SettingsPanelOption
		heading={$strings.rewrite_media_urls}
		description={$delivery_provider.rewrite_media_urls_desc}
		toggleName="serve-from-s3"
		bind:toggle={$settings["serve-from-s3"]}
	/>

	{#if $delivery_provider.delivery_domain_allowed}
		<SettingsPanelOption
			heading={$strings.delivery_domain}
			description={$delivery_provider.delivery_domain_desc}
			toggleName="enable-delivery-domain"
			bind:toggle={$settings["enable-delivery-domain"]}
			textName="delivery-domain"
			bind:text={$settings["delivery-domain"]}
			validator={domainValidator}
		/>
		{#if $delivery_provider.use_signed_urls_key_file_allowed && $settings[ "enable-delivery-domain" ]}
			<SettingsPanelOption
				heading={$delivery_provider.signed_urls_option_name}
				description={$delivery_provider.signed_urls_option_description}
				toggleName="enable-signed-urls"
				bind:toggle={$settings["enable-signed-urls"]}
			>
				<!-- Currently only CloudFront needs a key file for signing -->
				{#if $settings[ "enable-signed-urls" ]}
					<SettingsPanelOption
						heading={$delivery_provider.signed_urls_key_id_name}
						description={$delivery_provider.signed_urls_key_id_description}
						textName="signed-urls-key-id"
						bind:text={$settings["signed-urls-key-id"]}
						nested={true}
						first={true}
					/>

					<SettingsPanelOption
						heading={$delivery_provider.signed_urls_key_file_path_name}
						description={$delivery_provider.signed_urls_key_file_path_description}
						textName="signed-urls-key-file-path"
						bind:text={$settings["signed-urls-key-file-path"]}
						placeholder={$delivery_provider.signed_urls_key_file_path_placeholder}
						nested={true}
					/>

					<SettingsPanelOption
						heading={$delivery_provider.signed_urls_object_prefix_name}
						description={$delivery_provider.signed_urls_object_prefix_description}
						textName="signed-urls-object-prefix"
						bind:text={$settings["signed-urls-object-prefix"]}
						placeholder="private/"
						nested={true}
					/>
				{/if}
			</SettingsPanelOption>
		{/if}
	{/if}

	<SettingsPanelOption
		heading={$strings.force_https}
		description={$strings.force_https_desc}
		toggleName="force-https"
		bind:toggle={$settings["force-https"]}
	/>
</Panel>
