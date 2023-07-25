<script>
	import {urls} from "../js/stores";
	import SubNavItem from "./SubNavItem.svelte";

	export let name = "media";
	export let items = [];
	export let subpage = false;
	export let progress = false;

	$: displayItems = items.filter( ( page ) => page.title && (!page.hasOwnProperty( "enabled" ) || page.enabled() === true) );
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
