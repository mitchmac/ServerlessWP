<script>
	import {pages} from "../js/routes";
	import NavItem from "./NavItem.svelte";
	import OffloadStatus from "./OffloadStatus.svelte";
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
		<slot>
			<OffloadStatus/>
		</slot>
	</div>
</div>
