<?php
header("Content-Type: text/plain");

$json = file_get_contents ('/home/wvtohls/origin/movistar.json');
$json = json_decode ($json, true);

foreach($json as $canal=>$contenido) {
	$ch_id =  $canal;
	$name = $contenido["name"];
	echo "\n";
	$image = $contenido["logo"];
    echo '#EXTINF:-1 group-title="Movistar" tvg-logo="'.$image.'", '.$name.' ';
    echo "\n";
    $server = "http://{$_SERVER['SERVER_ADDR']}:18000/$ch_id/hls/playlist.m3u8";
    echo "$server";
	}