<script>
	import {urls} from "../js/stores";
	import SubNavItem from "./SubNavItem.svelte";

	/**
	 * @typedef {Object} Props
	 * @property {string} [name]
	 * @property {any} [items]
	 * @property {boolean} [subpage]
	 * @property {boolean} [progress]
	 */

	/** @type {Props} */
	let {
		name = "media",
		items = [],
		subpage = false,
		progress = false
	} = $props();

	let displayItems = $derived( items.filter( ( page ) => page.title && (!page.hasOwnProperty( "enabled" ) || page.enabled() === true) ) );
</script>

{#if displayItems}
	<ul class="subnav {name}" class:subpage class:progress>
		{#each displayItems as page, index}
			<SubNavItem {page}/>
			<!-- Show a progress indicator after all but the last item. -->
			{#if progress && index < (displayItems.length - 1)}
				<li class="step-arrow">
					<img src="{$urls.assets + 'img/icon/subnav-arrow.svg'}" alt="">
				</li>
			{/if}
		{/each}
	</ul>
{/if}
