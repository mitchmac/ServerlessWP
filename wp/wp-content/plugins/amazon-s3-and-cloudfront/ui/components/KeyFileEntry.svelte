<script>
	import {strings} from "../js/stores";

	export let provider;
	export let value = "";
	export let disabled = false;

	let name = "key-file";
	let label = $strings.key_file;
</script>

<p>{@html provider.enter_key_file_desc}</p>

<label class="input-label" for={name}>{label}</label>
<textarea id={name} name={name} bind:value {disabled} class:disabled rows="10"></textarea>
