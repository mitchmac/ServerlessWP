<script>
	import {cubicOut} from "svelte/easing";
	import {tweened} from "svelte/motion";

	export let percentComplete = 0;
	export let starting = false;
	export let running = false;
	export let paused = false;
	export let title = "";

	let progressTweened = tweened( 0, {
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

	$: progressTweened.set( getProgress( percentComplete ) );
	$: complete = percentComplete >= 100;
</script>

<div
	class="progress-bar"
	class:stripe={running && ! paused}
	class:animate={starting}
	{title}
	on:click|preventDefault
	on:mouseenter
	on:mouseleave
>
	<span class="indicator animate" class:complete class:running style="width: {$progressTweened}%"></span>
</div>
