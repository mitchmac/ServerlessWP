<script>
	import {link} from "svelte-spa-router";
	import active from "svelte-spa-router/active";

	let { tab } = $props();

	let focus = $state( false );
	let hover = $state( false );
</script>

<li class="nav-item" use:active={tab.routeMatcher ? tab.routeMatcher : tab.route} class:focus class:hover>
	<a
		href={tab.route}
		title={tab.title()}
		use:link
		onfocusin={() => focus = true}
		onfocusout={() => focus = false}
		onmouseenter={() => hover = true}
		onmouseleave={() => hover = false}
	>
		{tab.title()}
	</a>
</li>
