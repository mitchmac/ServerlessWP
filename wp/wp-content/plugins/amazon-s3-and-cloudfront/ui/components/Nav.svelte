<script>
	import {pages} from "../js/routes";
	import NavItem from "./NavItem.svelte";
	import OffloadStatus from "./OffloadStatus.svelte";
	/**
	 * @typedef {Object} Props
	 * @property {import("svelte").Snippet} [children]
	 */

	/** @type {Props} */
	let { children } = $props();
</script>

<div class="nav">
	<div class="items">
		<ul class="nav">
			{#each $pages as tab (tab.position)}
				{#if tab.nav && tab.title}
					<NavItem {tab}/>
				{/if}
			{/each}
		</ul>
		{#if children}
			{@render children()}
		{:else}
			<OffloadStatus/>
		{/if}
	</div>
</div>
