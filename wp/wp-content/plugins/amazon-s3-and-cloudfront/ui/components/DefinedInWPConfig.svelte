<script>
	import {strings} from "../js/stores";

	export let defined = false;
</script>

{#if defined}
	<p class="wp-config">{$strings.defined_in_wp_config}</p>
{/if}