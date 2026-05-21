<script>
	import {cubicOut} from "svelte/easing";
	import {Tween} from "svelte/motion";

	/**
	 * @typedef {Object} Props
	 * @property {number} [percentComplete]
	 * @property {boolean} [starting]
	 * @property {boolean} [running]
	 * @property {boolean} [paused]
	 * @property {string} [title]
	 * @property {function} [onclick]
	 * @property {function} [onmouseenter]
	 * @property {function} [onmouseleave]
	 */

	/** @type {Props} */
	let {
		percentComplete = 0,
		starting = false,
		running = false,
		paused = false,
		title = "",
		onclick,
		onmouseenter,
		onmouseleave
	} = $props();

	const progressTween = new Tween( 0, {
		duration: 400,
		easing: cubicOut
	} );

	/**
	 * Utility function for reactively getting the progress.
	 *
	 * @param {number} percent
	 *
	 * @return {number|*}
	 */
	function getProgress( percent ) {
		if ( percent < 1 ) {
			return 0;
		}

		if ( percent >= 100 ) {
			return 100;
		}

		return percent;
	}

	$effect( () => {
		progressTween.set( getProgress( percentComplete ) );
	} );
	let complete = $derived( percentComplete >= 100 );
</script>

<!-- TODO: Fix a11y. -->
<!-- svelte-ignore a11y_no_static_element_interactions -->
<!-- svelte-ignore a11y_click_events_have_key_events -->
<div
	class="progress-bar"
	class:stripe={running && ! paused}
	class:animate={starting}
	{title}
	{onclick}
	{onmouseenter}
	{onmouseleave}
>
	<span class="indicator animate" class:complete class:running style="width: {progressTween.current}%"></span>
</div>
