<script>
	import {counts, strings, urls} from "../js/stores";
	import {numToString} from "../js/numToString";
	import ProgressBar from "../components/ProgressBar.svelte";
	import OffloadStatusFlyout from "./OffloadStatusFlyout.svelte";

	// Controls whether flyout is visible or not.
	export let expanded = false;
	export let flyoutButton = {};
	export let hasFocus = false;

	/**
	 * Returns the numeric percentage progress for total offloaded media.
	 *
	 * @param {number} total
	 * @param {number} offloaded
	 *
	 * @return {number}
	 */
	function getPercentComplete( total, offloaded ) {
		if ( total < 1 || offloaded < 1 ) {
			return 0;
		}

		const percent = Math.floor( Math.abs( offloaded / total * 100 ) );

		if ( percent > 100 ) {
			return 100;
		}

		return percent;
	}

	$: percentComplete = getPercentComplete( $counts.total, $counts.offloaded );
	$: complete = percentComplete >= 100;

	/**
	 * Returns a formatted title string reflecting the current status.
	 *
	 * @param {number} percent
	 * @param {number} total
	 * @param {number} offloaded
	 * @param {string} description
	 *
	 * @return {string}
	 */
	function getTitle( percent, total, offloaded, description ) {
		return percent + "% (" + numToString( offloaded ) + "/" + numToString( total ) + ") " + description;
	}

	$: title = getTitle( percentComplete, $counts.total, $counts.offloaded, $strings.offloaded );

	/**
	 * Handles a click to toggle the flyout.
	 */
	function handleClick() {
		expanded = !expanded;
		flyoutButton.focus();

		// We've handled the click.
		return true;
	}

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
</script>

<div class="nav-status-wrapper" class:complete>
	<div
		class="nav-status"
		{title}
		on:click|preventDefault={handleClick}
		on:mouseenter={handleMouseEnter}
		on:mouseleave={handleMouseLeave}
	>
		{#if complete}
			<img
				class="icon type"
				src={$urls.assets + "img/icon/licence-checked.svg"}
				alt="{title}"
				{title}
			/>
		{/if}
		<p
			class="status-text"
			{title}
		>
			<strong>{percentComplete}%</strong>
			<span> {@html $strings.offloaded}</span>
		</p>
		<ProgressBar
			{percentComplete}
			{title}
		/>
	</div>
	<slot name="flyout">
		<OffloadStatusFlyout bind:expanded bind:hasFocus bind:buttonRef={flyoutButton}/>
	</slot>
</div>

<style>
	.nav-status-wrapper {
		position: relative;
	}
</style>
