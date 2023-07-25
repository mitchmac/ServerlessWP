<script>
	import {config, urls} from "../js/stores";

	$: header_img_url = $urls.assets + "img/brand/ome-medallion.svg";
</script>

<div class="header">
	<div class="header-wrapper">
		<img class="medallion" src={header_img_url} alt="{$config.title} logo">
		<h1>{$config.title}</h1>
		<div class="licence">
			<slot/>
		</div>
	</div>
</div>