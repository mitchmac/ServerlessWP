<script>
	import {link, location} from "svelte-spa-router";
	import {urls} from "../js/stores";

	export let page;

	let focus = false;
	let hover = false;

	$: showIcon = typeof page.noticeIcon === "string" && (["warning", "error"].includes( page.noticeIcon ));
	$: iconUrl = showIcon ? $urls.assets + "img/icon/tab-notifier-" + page.noticeIcon + ".svg" : "";

</script>

<li class="subnav-item" class:active={$location === page.route} class:focus class:hover class:has-icon={showIcon}>
	<a
		href={page.route}
		title={page.title()}
		use:link
		on:focusin={() => focus = true}
		on:focusout={() => focus = false}
		on:mouseenter={() => hover = true}
		on:mouseleave={() => hover = false}
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
