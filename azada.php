<?php

/*
 * Azada 0.1b
 * php web-based proxy
 * https://github.com/hamidsamak/azada
 */

error_reporting(0);
set_time_limit(15);

if (isset($_GET['url']) && empty($_GET['url']) === false) {
	foreach ($_GET as $key => $value)
		if (substr($key, 0, 4) === 'amp;' && strlen($key) > 4) {
			$_GET[substr($key, 4)] = $value;

			unset($_GET[$key]);
		}

	$url = urldecode($_GET['url']);
	$rot13 = isset($_GET['rot13']);
	$noimg = isset($_GET['noimg']);
	$nojs = isset($_GET['nojs']);
	$base64 = isset($_GET['base64']);

	if ($rot13)
		$url = str_rot13($url);

	if ($base64)
		$url = base64_decode($url);

	if ($result = get_contents($url, true)) {
		$data = $result[0];
		$info = $result[1];
		$url = $info['url'];

		$parse = parse_url($url);

		if (isset($info['content_type']) && empty($info['content_type']) === false) {
			header('Content-type: ' . $info['content_type']);

			if (isset($info['original_headers']['Content-Encoding']))
				header('Content-Encoding: ' . $info['original_headers']['Content-Encoding']);

			if (substr($info['content_type'], 0, 5) != 'text/') {
				$file_name = basename($info['url']);

				if (($pos = strpos($file_name, '?')) !== false)
					$file_name = substr($file_name, 0, $pos);

				header('Content-Disposition: attachment; filename="' . $file_name . '"');
			}

			if ($info['content_type'] == 'text/css') {
				preg_match_all("/url\(['|\"]?(.*?)['|\"]?\)/", $data, $matches);

				if (isset($matches[1]) && count($matches[1]) > 0) {
					$matches[1] = array_unique($matches[1]);

					foreach ($matches[1] as $value) {
						$address = $value;
						$parse = parse_url($url);

						if (substr($address, 0, 1) == '/')
							$address = $parse['scheme'] . '://' . $parse['host'] . $address;
						else
							$address = $parse['scheme'] . '://' . $parse['host'] . dirname($parse['path']) . '/' . $address;

						if ($base64)
							$address = base64_encode($address);

						if ($rot13)
							$address = str_rot13($address);

						$data = str_replace($value, $_SERVER['PHP_SELF'] . '?url=' . $address . ($base64 ? '&base64=1' : null) . ($rot13 ? '&rot13=1' : null), $data);
					}
				}
			}
		}
		
		if (strtolower(substr($data, 0, 9)) === '<!doctype') {
			if (isset($parse['scheme']) === false)
				$parse['scheme'] = 'http';
			if (isset($parse['host']) === false)
				$parse['host'] = $parse['path'];

			$base_url = $parse['scheme'] . '://' . $parse['host'];

			$doc = new DOMDocument();
			
			$doc->loadHTML($data);

			foreach (array('a' => 'href', 'link' => 'href', 'img' => 'src', 'script' => 'src', 'form' => 'action') as $tag_name => $attribute)
				foreach ($doc->getElementsByTagName($tag_name) as $tag) {
					if ($tag_name === 'script' && $nojs === true) {
						$tag->parentNode->removeChild($tag);
						
						continue;
					} else if ($tag_name === 'img' && $noimg === true) {
						$tag->setAttribute('src', '#');

						continue;
					}

					$value = $tag->getAttribute($attribute);
					
					$parse = parse_url($value);
					if (isset($parse['scheme']) === false)
						$value = $base_url . (substr($value, 0, 1) === '/' ? null : '/') . $value;

					if ($base64)
						$value = base64_encode($value);

					if ($rot13)
						$value = str_rot13($value);

					$tag->setAttribute($attribute, $_SERVER['PHP_SELF'] . '?url=' . $value . ($base64 ? '&base64=1' : null) . ($rot13 ? '&rot13=1' : null) . ($noimg ? '&noimg=1' : null) . ($nojs ? '&nojs=1' : null));
				}

			$data = $doc->saveHTML();

			if ($nojs === true)
				$data = str_ireplace(array('<noscript>', '</noscript>'), '', $data);

			echo $data;
		} else
			echo $data;
	} else
		echo '<strong>Azada error:</strong> Unreachable host.';

	exit;
}

if (file_exists(__DIR__ . '/.cookie'))
	unlink('.cookie');

echo '<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Azada</title>
<style type="text/css">
header, section, footer {
	width: 50%;
	margin: 0 auto;
	padding-top: 10px;
	max-width: 1000px;
}
header {
	padding: 0;
	border-bottom: 1px solid #ccc;
}
footer {
	border-top: 1px solid #ccc;
}
#url {
	width: 80%;
}
#browse {
	width: 15%;
}
@media only screen and (max-width: 768px) {
	header, section, footer {
		width: 100%;
	}
}
</style>
<script type="text/javascript">
var checks;

function $(id) {
	return document.getElementById(id);
}
function options() {
	var url = $("url").value;
	var result = url;

	checks = {
		base64: $("base64").checked,
		rot13: $("rot13").checked,
		noimg: $("noimg").checked,
		nojs: $("nojs").checked,
		new_window: $("new_window").checked
	}

	if (checks.base64 == true)
		result = window.btoa(result);
	else
		$("base64").removeAttribute("name");

	if (checks.rot13 == true)
		result = result.replace(/[a-zA-Z]/g,function(c){return String.fromCharCode((c<="Z"?90:122)>=(c=c.charCodeAt(0)+13)?c:c-26);});
	else
		$("rot13").removeAttribute("name");

	if (checks.new_window == true)
		$("form").setAttribute("target", "_blank");

	$("form").innerHTML = "<input name=\"url\" type=\"hidden\" value=\"" + result + "\">" + $("form").innerHTML;
	$("url").removeAttribute("name");
	$("url").value = url;

	for (i in checks)
		$(i).checked = checks[i];

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
	<input id="url" name="url" type="text" value="" placeholder="URL&hellip;"> <button id="browse" type="submit" disabled>Browse</button><noscript> Please enable javascript</noscript><br>
	<label><input id="base64" name="base64" type="checkbox" value="1" checked> Base64 encode</label><br>
	<label><input id="rot13" name="rot13" type="checkbox" value="1" checked> ROT13 encode</label><br>
	<label><input id="noimg" name="noimg" type="checkbox" value="1"> Block images</label><br>
	<label><input id="nojs" name="nojs" type="checkbox" value="1"> Remove Javascript</label><br>
	<label><input id="new_window" type="checkbox" value="1"> Open in new window</label>
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
	curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/.cookie');
	curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/.cookie');
	curl_setopt($ch, CURLOPT_HEADER, true);

	if (isset($_POST) && count($_POST) > 0) {
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $_POST);
	}
	
	$data = curl_exec($ch);

	while (substr($data, 0, 4) == 'HTTP') {
		$seperator_position = strpos($data, "\r\n\r\n");
		$headers = substr($data, 0, $seperator_position);
		$data = substr($data, $seperator_position + 4);
	}

	if ($return_info === true) {
		$info = curl_getinfo($ch);

		$headers = explode("\n", $headers);
		foreach ($headers as $header) {
			$seperator_position = strpos($header, ':');

			if ($seperator_position === false)
				$info['original_headers'][] = $header;
			else
				$info['original_headers'][substr($header, 0, $seperator_position)] = trim(substr($header, $seperator_position + 1));
		}
	}

	curl_close($ch);

	return isset($info) ? array($data, $info) : $data;
}

?>