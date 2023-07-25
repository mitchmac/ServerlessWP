<script>
	import {strings, urls} from "../js/stores";
	import Header from "../components/Header.svelte";
</script>

<Header>
	<a href={$urls.header_discount} class="button btn-lg btn-primary">{$strings.get_licence_discount_text}</a>
</Header>