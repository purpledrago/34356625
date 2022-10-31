<?php
/**
*
* @ This file is created by http://DeZender.Net
* @ deZender (PHP7 Decoder for ionCube Encoder)
*
* @ Version			:	4.1.0.0
* @ Author			:	DeZender
* @ Release on		:	15.05.2020
* @ Official site	:	http://DeZender.Net
*
*/

include '../includes/functions.php';
ini_set('display_errors', 0);
$rReturn = [
	'channels' => [],
	'time'     => time()
];

foreach (scandir(MAIN_DIR . 'video/') as $rChannel) {

	#if (strlen($rChannel) == 24) {
		$rDatabase = openCache($rChannel);
		$rPID = getCache($rDatabase, 'php_pid');
		if ($rPID && file_exists('/proc/' . $rPID) && !file_exists(MAIN_DIR . ('video/' . $rChannel . '/.stop'))) {
			$rLastUpdate = filemtime(MAIN_DIR . ('hls/' . $rChannel . '/hls/playlist.m3u8'));

			if (!$rLastUpdate) {
				$rLastUpdate = NULL;
			}

			$rHLSTime = filemtime(MAIN_DIR . ('video/' . $rChannel . '/.ffmpeg'));

			if (!$rHLSTime) {
				$rHLSTime = NULL;
			}

			$rStreamInfo = getCache($rDatabase, 'stream_info');
			if (!$rStreamInfo || (strlen($rStreamInfo) == 0)) {
				$rStreamInfo = NULL;
			}

			$rLoopAt = getCache($rDatabase, 'loop_at');
			$rReturn['channels'][$rChannel] = ['started' => true, 'time_started' => $rDatabase['php_pid']['expires'] - $rCacheTime, 'time_updated' => $rLastUpdate, 'time_hls' => $rHLSTime, 'stream_info' => $rStreamInfo, 'loop_at' => $rLoopAt];
		}
	#}
}

echo json_encode($rReturn);

?>