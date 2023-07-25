<script>
	import {link} from "svelte-spa-router";
	import active from "svelte-spa-router/active";

	export let tab;

	let focus = false;
	let hover = false;
</script>

<li class="nav-item" use:active={tab.routeMatcher ? tab.routeMatcher : tab.route} class:focus class:hover>
	<a
		href={tab.route}
		title={tab.title()}
		use:link
		on:focusin={() => focus = true}
		on:focusout={() => focus = false}
		on:mouseenter={() => hover = true}
		on:mouseleave={() => hover = false}
	>
		{tab.title()}
	</a>
</li>
