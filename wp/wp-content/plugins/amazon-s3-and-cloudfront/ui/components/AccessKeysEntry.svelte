<script>
	import {strings} from "../js/stores";

	export let provider;
	export let accessKeyId = "";
	export let secretAccessKey = "";
	export let disabled = false;

	let accessKeyIdName = "access-key-id";
	let accessKeyIdLabel = $strings.access_key_id;

	let secretAccessKeyName = "secret-access-key";
	let secretAccessKeyLabel = $strings.secret_access_key;
</script>

<p>{@html provider.enter_access_keys_desc}</p>

<label class="input-label" for={accessKeyIdName}>{accessKeyIdLabel}</label>
<input
	type="text"
	id={accessKeyIdName}
	name={accessKeyIdName}
	bind:value={accessKeyId}
	minlength="20"
	size="20"
	{disabled}
	class:disabled
>

<label class="input-label" for={secretAccessKeyName}>{secretAccessKeyLabel}</label>
<input
	type="text"
	id={secretAccessKeyName}
	name={secretAccessKeyName}
	bind:value={secretAccessKey}
	autocomplete="off"
	minlength="40"
	size="40"
	{disabled}
	class:disabled
>
