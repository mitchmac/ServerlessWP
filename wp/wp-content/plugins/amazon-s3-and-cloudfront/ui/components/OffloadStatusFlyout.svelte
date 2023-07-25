<script>
	import {
		counts,
		offloadRemainingUpsell,
		summaryCounts,
		strings,
		urls,
		api,
		state
	} from "../js/stores";
	import {numToString} from "../js/numToString";
	import {delayMin} from "../js/delay";
	import Button from "./Button.svelte";
	import Panel from "./Panel.svelte";
	import PanelRow from "./PanelRow.svelte";

	export let expanded = false;
	export let buttonRef = {};
	export let panelRef = {};
	export let hasFocus = false;
	export let refreshing = false;

	/**
	 * Keep track of when a child control gets mouse focus.
	 */
	function handleMouseEnter() {
		hasFocus = true;
	}

	/**
	 * Keep track of when a child control loses mouse focus.
	 */
	function handleMouseLeave() {
		hasFocus = false;
	}

	/**
	 * When the panel is clicked, select the first focusable element
	 * so that clicking outside the panel triggers a lost focus event.
	 */
	function handlePanelClick() {
		hasFocus = true;

		const firstFocusable = panelRef.querySelector( "a:not([tabindex='-1']),button:not([tabindex='-1'])" );

		if ( firstFocusable ) {
			firstFocusable.focus();
		}
	}

	/**
	 * When either the button or panel completely lose focus, close the flyout.
	 *
	 * @param {FocusEvent} event
	 *
	 * @return {boolean}
	 */
	function handleFocusOut( event ) {
		if ( !expanded ) {
			return false;
		}

		// Mouse click and OffloadStatus control/children no longer have mouse focus.
		if ( event.relatedTarget === null && !hasFocus ) {
			expanded = false;
		}

		// Keyboard focus change and new focused control isn't within OffloadStatus/Flyout.
		if (
			event.relatedTarget !== null &&
			event.relatedTarget !== buttonRef &&
			!panelRef.contains( event.relatedTarget )
		) {
			expanded = false;
		}
	}

	/**
	 * Handle cancel event from panel and button.
	 */
	function handleCancel() {
		buttonRef.focus();
		expanded = false;
	}

	/**
	 * Manually refresh the media counts.
	 *
	 * @return {Promise<void>}
	 */
	async function handleRefresh() {
		let start = Date.now();
		refreshing = true;

		let params = {
			refreshMediaCounts: true
		};

		let json = await api.get( "state", params );
		await delayMin( start, 1000 );
		state.updateState( json );

		refreshing = false;
		buttonRef.focus();
	}
</script>

<Button
	expandable
	{expanded}
	on:click={() => expanded = !expanded}
	title={expanded ? $strings.hide_details : $strings.show_details}
	bind:ref={buttonRef}
	on:focusout={handleFocusOut}
	on:cancel={handleCancel}
/>

{#if expanded}
	<Panel
		multi
		flyout
		refresh
		{refreshing}
		heading={$strings.offload_status_title}
		refreshDesc={$strings.refresh_media_counts_desc}
		bind:ref={panelRef}
		on:focusout={handleFocusOut}
		on:mouseenter={handleMouseEnter}
		on:mouseleave={handleMouseLeave}
		on:mousedown={handleMouseEnter}
		on:click={handlePanelClick}
		on:cancel={handleCancel}
		on:refresh={handleRefresh}
	>
		<PanelRow class="summary">
			<table>
				<thead>
				<tr>
					<th>{$strings.summary_type_title}</th>
					<th class="numeric">{$strings.summary_offloaded_title}</th>
					<th class="numeric">{$strings.summary_not_offloaded_title}</th>
				</tr>
				</thead>

				<tbody>
				{#each $summaryCounts as summary (summary.type)}
					<tr>
						<td>{summary.name}</td>
						{#if summary.offloaded_url}
							<td class="numeric">
								<a href="{summary.offloaded_url}">{numToString( summary.offloaded )}</a>
							</td>
						{:else}
							<td class="numeric">{numToString( summary.offloaded )}</td>
						{/if}
						{#if summary.not_offloaded_url}
							<td class="numeric">
								<a href="{summary.not_offloaded_url}">{numToString( summary.not_offloaded )}</a>
							</td>
						{:else}
							<td class="numeric">{numToString( summary.not_offloaded )}</td>
						{/if}
					</tr>
				{/each}
				</tbody>

				{#if $summaryCounts.length > 1}
					<tfoot>
					<tr>
						<td>{$strings.summary_total_row_title}</td>
						<td class="numeric">{numToString( $counts.offloaded )}</td>
						<td class="numeric">{numToString( $counts.not_offloaded )}</td>
					</tr>
					</tfoot>
				{/if}
			</table>
		</PanelRow>

		<slot name="footer">
			<PanelRow footer class="upsell">
				{#if $offloadRemainingUpsell}
					<p>{@html $offloadRemainingUpsell}</p>
				{/if}
				<a href={$urls.upsell_discount} class="button btn-sm btn-primary licence" target="_blank">
					<img src={$urls.assets + "img/icon/stars.svg"} alt="stars icon" style="margin-right: 5px;">
					{$strings.offload_remaining_upsell_cta}
				</a>
			</PanelRow>
		</slot>
	</Panel>
{/if}
