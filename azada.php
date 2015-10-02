<?php

/*
 * Azada 0.1b
 * php web-based proxy
 * https://github.com/hamidsamak/azada
 */

error_reporting(0);

if (isset($_GET['url']) && empty($_GET['url']) === false) {
	$url = urldecode($_GET['url']);
	$rot13 = isset($_GET['rot13']);
	$base64 = isset($_GET['base64']);

	if ($rot13)
		$url = str_rot13($url);

	if ($base64)
		$url = base64_decode($url);

	if ($result = get_contents($url, true)) {
		$data = trim($result[0]);
		$info = $result[1];

		$parse = parse_url($url);

		if (isset($info['content_type']) && empty($info['content_type']) === false)
			header('Content-type: ' . $info['content_type']);
		
		if (strtolower(substr($data, 0, 9)) === '<!doctype') {
			if (isset($parse['scheme']) === false)
				$parse['scheme'] = 'http';
			if (isset($parse['host']) === false)
				$parse['host'] = $parse['path'];

			$base_url = $parse['scheme'] . '://' . $parse['host'];

			$doc = new DOMDocument();
			
			$doc->loadHTML($data);

			foreach (array('a' => 'href', 'link' => 'href', 'img' => 'src', 'script' => 'src', 'form' => 'action') as $name => $attribute)
				foreach ($doc->getElementsByTagName($name) as $link) {
					$value = $link->getAttribute($attribute);
					
					$parse = parse_url($value);
					if (isset($parse['scheme']) === false)
						$value = $base_url . (substr($value, 0, 1) === '/' ? null : '/') . $value;

					if ($base64)
						$value = base64_encode($value);

					if ($rot13)
						$value = str_rot13($value);

					$link->setAttribute($attribute, $_SERVER['PHP_SELF'] . '?url=' . $value . ($base64 ? '&base64=1' : null) . ($rot13 ? '&rot13=1' : null));
				}

			echo $doc->saveHTML();
		} else
			die($data);
	} else
		die('<strong>Azada error:</strong> Unreachable host.');

	exit;
}

if (file_exists(__DIR__ . '/cookie.txt'))
	unlink('cookie.txt');

echo '<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0"> 
<title>Azada</title>
<script type="text/javascript">
function $(id) {
	return document.getElementById(id);
}
function options() {
	var url = $("url").value;
	var result = url;

	if ($("base64").checked == true)
		result = window.btoa(result);
	else
		$("base64").removeAttribute("name");

	if ($("rot13").checked == true)
		result = result.replace(/[a-zA-Z]/g,function(c){return String.fromCharCode((c<="Z"?90:122)>=(c=c.charCodeAt(0)+13)?c:c-26);});
	else
		$("rot13").removeAttribute("name");

	$("form").innerHTML = "<input name=\"url\" type=\"hidden\" value=\"" + result + "\">" + $("form").innerHTML;
	$("url").removeAttribute("name");
	$("url").value = url;
	
	return true;
}

window.onload = function(){
	$("browse").removeAttribute("disabled");
}
</script>
</head>
<body>
<header><h1>Azada</h1></header>
<section>
	<form id="form" method="get" action="' . $_SERVER['PHP_SELF'] . '" onsubmit="return options();">
	URL: <input id="url" name="url" type="text" value=""> <button id="browse" type="submit" disabled>Browse</button><noscript> Please enable javascript</noscript><br>
	<label><input id="base64" name="base64" type="checkbox" value="1" checked> Base64 encode</label><br>
	<label><input id="rot13" name="rot13" type="checkbox" value="1" checked> ROT13 encode</label><br>
	<!--label><input id="new_window" type="checkbox" value="1"> Open in new window</label-->
	</form>
</section>
<br>
<footer><a href="https://github.com/hamidsamak/azada">Azada 0.1b</a></footer>
</body>
</html>';

function get_contents($url, $return_info = false) {
	$ch = curl_init();

	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookie.txt');
	curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie.txt');

	if (isset($_POST) && count($_POST) > 0) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_POST);
	}
	
	$data = curl_exec($ch);

	if ($return_info === true)
		$info = curl_getinfo($ch);

	curl_close($ch);

	return isset($info) ? array($data, $info) : $data;
}

?>