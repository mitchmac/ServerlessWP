<script>
	import {
		createEventDispatcher,
		getContext,
		hasContext,
		onMount
	} from "svelte";
	import {writable} from "svelte/store";
	import {slide} from "svelte/transition";
	import {
		api,
		settings,
		defined_settings,
		strings,
		storage_provider,
		urls,
		current_settings,
		needs_refresh,
		revalidatingSettings,
		state
	} from "../js/stores";
	import {scrollIntoView} from "../js/scrollIntoView";
	import {
		scrollNotificationsIntoView
	} from "../js/scrollNotificationsIntoView";
	import {needsRefresh} from "../js/needsRefresh";
	import SubPage from "./SubPage.svelte";
	import Panel from "./Panel.svelte";
	import PanelRow from "./PanelRow.svelte";
	import TabButton from "./TabButton.svelte";
	import BackNextButtonsRow from "./BackNextButtonsRow.svelte";
	import RadioButton from "./RadioButton.svelte";
	import Loading from "./Loading.svelte";
	import DefinedInWPConfig from "./DefinedInWPConfig.svelte";

	const dispatch = createEventDispatcher();

	// Parent page may want to be locked.
	let settingsLocked = writable( false );

	if ( hasContext( "settingsLocked" ) ) {
		settingsLocked = getContext( "settingsLocked" );
	}

	// Keep track of where we were at prior to any changes made here.
	let initialSettings = $current_settings;

	if ( hasContext( "initialSettings" ) ) {
		initialSettings = getContext( "initialSettings" );
	}

	// As this page does not directly alter the settings store until done,
	// we need to keep track of any changes made elsewhere and prompt
	// the user to refresh the page.
	let saving = false;
	const previousSettings = { ...$current_settings };
	const previousDefines = { ...$defined_settings };

	$: {
		$needs_refresh = $needs_refresh || needsRefresh( saving, previousSettings, $current_settings, previousDefines, $defined_settings );
	}

	let bucketSource = "existing";
	let enterOrSelectExisting = "enter";

	// If $defined_settings.bucket set, must use it, and disable change.
	let newBucket = $settings.bucket;
	$: defined = $defined_settings.includes( "bucket" );
	$: disabled = defined || $needs_refresh || $settingsLocked;

	// If $defined_settings.region set, must use it, and disable change.
	let newRegion = $settings.region;
	$: newRegionDefined = $defined_settings.includes( "region" );
	$: newRegionDisabled = newRegionDefined || $needs_refresh || $settingsLocked;

	/**
	 * Handles clicking the Existing radio button.
	 */
	function handleExisting() {
		if ( disabled ) {
			return;
		}

		bucketSource = "existing";
	}

	/**
	 * Handles clicking the New radio button.
	 */
	function handleNew() {
		if ( disabled ) {
			return;
		}

		bucketSource = "new";
	}

	/**
	 * Calls the API to get a list of existing buckets for the currently selected storage provider and region (if applicable).
	 *
	 * @param {string} region
	 *
	 * @return {Promise<*[]>}
	 */
	async function getBuckets( region ) {
		let params = {};

		if ( $storage_provider.region_required ) {
			params = { region: region };
		}

		let data = await api.get( "buckets", params );

		if ( data.hasOwnProperty( "buckets" ) ) {
			if ( data.buckets.filter( ( bucket ) => bucket.Name === newBucket ).length === 0 ) {
				newBucket = "";
			}

			return data.buckets;
		}

		newBucket = "";

		return [];
	}

	/**
	 * Calls the API to create a new bucket with the currently entered name and selected region.
	 *
	 * @return {Promise<boolean>}
	 */
	async function createBucket() {
		let data = await api.post( "buckets", {
			bucket: newBucket,
			region: newRegion
		} )

		if ( data.hasOwnProperty( "saved" ) ) {
			return data.saved;
		}

		return false;
	}

	/**
	 * Potentially returns a reason that the provided bucket name is invalid.
	 *
	 * @param {string} bucket
	 * @param {string} source Either "existing" or "new".
	 * @param {string} existingType Either "enter" or "select".
	 *
	 * @return {string}
	 */
	function getInvalidBucketNameMessage( bucket, source, existingType ) {
		// If there's an invalid region defined, don't even bother looking at bucket name.
		if ( newRegionDefined && (newRegion.length === 0 || !$storage_provider.regions.hasOwnProperty( newRegion )) ) {
			return $strings.defined_region_invalid;
		}

		const bucketNamePattern = source === "new" ? /[^a-z0-9.\-]/ : /[^a-zA-Z0-9.\-_]/;

		let message = "";

		if ( bucket.trim().length < 1 ) {
			if ( source === "existing" && existingType === "select" ) {
				message = $strings.no_bucket_selected;
			} else {
				message = $strings.create_bucket_name_missing;

			}
		} else if ( true === bucketNamePattern.test( bucket ) ) {
			message = source === "new" ? $strings.create_bucket_invalid_chars : $strings.select_bucket_invalid_chars;
		} else if ( bucket.length < 3 ) {
			message = $strings.create_bucket_name_short;
		} else if ( bucket.length > 63 ) {
			message = $strings.create_bucket_name_long;
		}

		return message;
	}

	$: invalidBucketNameMessage = getInvalidBucketNameMessage( newBucket, bucketSource, enterOrSelectExisting );

	/**
	 * Returns text to be used on Next button.
	 *
	 * @param {string} source Either "existing" or "new".
	 * @param {string} existingType Either "enter" or "select".
	 *
	 * @return {string}
	 */
	function getNextText( source, existingType ) {
		if ( source === "existing" && existingType === "enter" ) {
			return $strings.save_enter_bucket;
		}

		if ( source === "existing" && existingType === "select" ) {
			return $strings.save_select_bucket;
		}

		if ( source === "new" ) {
			return $strings.save_new_bucket;
		}

		return $strings.next;
	}

	$: nextText = getNextText( bucketSource, enterOrSelectExisting );

	/**
	 * Handles a Next button click.
	 *
	 * @return {Promise<void>}
	 */
	async function handleNext() {
		if ( bucketSource === "new" && false === await createBucket() ) {
			scrollNotificationsIntoView();
			return;
		}

		saving = true;
		state.pausePeriodicFetch();

		$settings.bucket = newBucket;
		$settings.region = newRegion;
		const result = await settings.save();

		// If something went wrong, don't move onto next step.
		if ( result.hasOwnProperty( "saved" ) && !result.saved ) {
			settings.reset();
			saving = false;
			await state.resumePeriodicFetch();

			scrollNotificationsIntoView();
			return;
		}

		$revalidatingSettings = true;
		const statePromise = state.resumePeriodicFetch();

		result.bucketSource = bucketSource;
		result.initialSettings = initialSettings;

		dispatch( "routeEvent", {
			event: "settings.save",
			data: result,
			default: "/"
		} );

		// Just make sure periodic state fetch promise is done with,
		// even though we don't really care about it.
		await statePromise;
		$revalidatingSettings = false;
	}

	onMount( () => {
		// Default to first region in storage provider if not defined and not set or not valid.
		if ( !newRegionDefined && (newRegion.length === 0 || !$storage_provider.regions.hasOwnProperty( newRegion )) ) {
			newRegion = Object.keys( $storage_provider.regions )[ 0 ];
		}
	} );
</script>

<SubPage name="bucket-settings" route="/storage/bucket">
	<Panel heading={$strings.bucket_source_title} multi {defined}>
		<PanelRow class="body flex-row tab-buttons">
			<TabButton
				active={bucketSource === "existing"}
				{disabled}
				text={$strings.use_existing_bucket}
				on:click={handleExisting}
			/>
			<TabButton
				active={bucketSource === "new"}
				{disabled}
				text={$strings.create_new_bucket}
				on:click={handleNew}
			/>
		</PanelRow>
	</Panel>

	{#if bucketSource === "existing"}
		<Panel heading={$strings.existing_bucket_title} storageProvider={$storage_provider} multi {defined}>
			<PanelRow class="body flex-column">
				<div class="flex-row align-center row radio-btns">
					<RadioButton bind:selected={enterOrSelectExisting} value="enter" list {disabled}>{$strings.enter_bucket}</RadioButton>
					<RadioButton bind:selected={enterOrSelectExisting} value="select" list {disabled}>{$strings.select_bucket}</RadioButton>
				</div>

				{#if enterOrSelectExisting === "enter"}
					<div class="flex-row align-center row">
						<div class="new-bucket-details flex-column">
							<label class="input-label" for="bucket-name">{$strings.bucket_name}</label>
							<input
								type="text"
								id="bucket-name"
								class="bucket-name"
								name="bucket"
								minlength="3"
								placeholder={$strings.enter_bucket_name_placeholder}
								bind:value={newBucket}
								class:disabled
								{disabled}
							>
						</div>
						{#if $storage_provider.region_required}
							<div class="region flex-column">
								<label class="input-label" for="region">
									{$strings.region}&nbsp;<DefinedInWPConfig defined={newRegionDefined}/>
								</label>
								<select name="region" id="region" bind:value={newRegion} disabled={newRegionDisabled} class:disabled={newRegionDisabled}>
									{#each Object.entries( $storage_provider.regions ) as [regionKey, regionName], index}
										<option
											value={regionKey}
											selected={regionKey === newRegion}
										>
											{regionName}
										</option>
									{/each}
								</select>
							</div>
						{/if}
					</div>
				{/if}

				{#if enterOrSelectExisting === "select"}
					{#if $storage_provider.region_required}
						<label class="input-label" for="list-region">
							{$strings.region}&nbsp;<DefinedInWPConfig defined={newRegionDefined}/>
						</label>
						<select name="region" id="list-region" bind:value={newRegion} disabled={newRegionDisabled} class:disabled={newRegionDisabled}>
							{#each Object.entries( $storage_provider.regions ) as [regionKey, regionName], index}
								<option
									value={regionKey}
									selected={regionKey === newRegion}
								>
									{regionName}
								</option>
							{/each}
						</select>
					{/if}
					{#await getBuckets( newRegion )}
						<Loading/>
					{:then buckets}
						<ul class="bucket-list">
							{#if buckets.length}
								{#each buckets as bucket}
									<li
										class="row"
										class:active={newBucket === bucket.Name}
										on:click={() => newBucket = bucket.Name}
										use:scrollIntoView={newBucket === bucket.Name}
										data-bucket-name={bucket.Name}
									>
										<img class="icon bucket" src="{$urls.assets + 'img/icon/bucket.svg'}" alt={$strings.bucket_icon}>
										<p>{bucket.Name}</p>
										{#if newBucket === bucket.Name}
											<img class="icon status" src="{$urls.assets + 'img/icon/licence-checked.svg'}" type="image/svg+xml" alt={$strings.selected_desc}>
										{/if}
									</li>
								{/each}
							{:else}
								<li class="row nothing-found">
									<p>{$strings.nothing_found}</p>
								</li>
							{/if}
						</ul>
					{/await}
				{/if}
				{#if invalidBucketNameMessage}
					<p class="input-error" transition:slide|local>{invalidBucketNameMessage}</p>
				{/if}
			</PanelRow>
		</Panel>
	{/if}

	{#if bucketSource === "new"}
		<Panel heading={$strings.new_bucket_title} storageProvider={$storage_provider} multi {defined}>
			<PanelRow class="body flex-column">
				<div class="flex-row align-center row">
					<div class="new-bucket-details flex-column">
						<label class="input-label" for="new-bucket-name">{$strings.bucket_name}</label>
						<input
							type="text"
							id="new-bucket-name"
							class="bucket-name"
							name="bucket"
							minlength="3"
							placeholder={$strings.enter_bucket_name_placeholder}
							bind:value={newBucket}
							class:disabled
							{disabled}
						>
					</div>
					<div class="region flex-column">
						<label class="input-label" for="new-region">
							{$strings.region}&nbsp;<DefinedInWPConfig defined={newRegionDefined}/>
						</label>
						<select name="region" id="new-region" bind:value={newRegion} disabled={newRegionDisabled} class:disabled={newRegionDisabled}>
							{#each Object.entries( $storage_provider.regions ) as [regionKey, regionName], index}
								<option
									value={regionKey}
									selected={regionKey === newRegion}
								>
									{regionName}
								</option>
							{/each}
						</select>
					</div>
				</div>
				{#if invalidBucketNameMessage}
					<p class="input-error" transition:slide|local>{invalidBucketNameMessage}</p>
				{/if}
			</PanelRow>
		</Panel>
	{/if}

	<BackNextButtonsRow
		on:next={handleNext}
		{nextText}
		nextDisabled={invalidBucketNameMessage || $needs_refresh || $settingsLocked}
		nextTitle={invalidBucketNameMessage}
	/>
</SubPage>
