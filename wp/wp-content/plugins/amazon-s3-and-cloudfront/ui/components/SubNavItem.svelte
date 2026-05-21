<script>
	import {link, location} from "svelte-spa-router";
	import {urls} from "../js/stores";

	let { page } = $props();

	let focus = $state( false );
	let hover = $state( false );

	let showIcon = $derived( typeof page.noticeIcon === "string" && (["warning", "error"].includes( page.noticeIcon )) );
	let iconUrl = $derived( showIcon ? $urls.assets + "img/icon/tab-notifier-" + page.noticeIcon + ".svg" : "" );

</script>

<li class="subnav-item" class:active={$location === page.route} class:focus class:hover class:has-icon={showIcon}>
	<a
		href={page.route}
		title={page.title()}
		use:link
		onfocusin={() => focus = true}
		onfocusout={() => focus = false}
		onmouseenter={() => hover = true}
		onmouseleave={() => hover = false}
	>
		{page.title()}
		{#if showIcon}
			<div class="notice-icon-wrapper notice-icon-{page.noticeIcon}">
				<img class="notice-icon" src="{iconUrl}" alt="{page.noticeIcon}">
			</div>
		{/if}
	</a>
</li>

<style>
	.notice-icon-wrapper {
		display: inline-block;
		min-width: 1.1875rem;

	}

	.notice-icon {
		margin-left: 2px;
		margin-top: -1.5px;
		vertical-align: middle;
	}
</style>
