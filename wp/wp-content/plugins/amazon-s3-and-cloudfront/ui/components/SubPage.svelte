<script>
	import active from "svelte-spa-router/active";

	export let name = "";
	export let route = "/";
</script>

<div class="{name}" use:active={route}>
	<slot/>
</div>
