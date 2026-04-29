<script>
	import {strings} from "../js/stores";

	/**
	 * @typedef {Object} Props
	 * @property {any} provider
	 * @property {string} [value]
	 * @property {boolean} [disabled]
	 */

	/** @type {Props} */
	let { provider, value = $bindable( "" ), disabled = false } = $props();

	let name = "key-file";
	let label = $strings.key_file;
</script>

<p>{@html provider.enter_key_file_desc}</p>

<label class="input-label" for={name}>{label}</label>
<textarea id={name} name={name} bind:value {disabled} class:disabled rows="10"></textarea>
