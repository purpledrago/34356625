<?php
include 'functions.php';
set_time_limit(0);
ini_set('default_socket_timeout', 15);
ini_set('memory_limit', -1);
if (isset($argv[1]) && (strtoupper($argv[1]) == 'START')) {
	gc_enable();
	$rWait = 10000;
	$rFailWait = 15;
	$rKeyWait = 5;
	$rScript = 'movistar';
	$rLanguage = 'spa';
	if (isset($argv[2])) 
	{ 
		$rChannel = $argv[2];
		plog('Shutting down previous instances.');
		exec('kill -9 `ps -ef | grep \'DRM_' . $rChannel . '\' | grep -v grep | awk \'{print $2}\'`');
		cli_set_process_title('DRM_' . $rChannel);
		$rDatabase = deleteCache($rChannel);
		$rDatabase = setCache($rDatabase, 'php_pid', getmypid());
		saveCache($rChannel, $rDatabase);
		$rDatabase = setCache($rDatabase, 'time_start', time());
		saveCache($rChannel, $rDatabase);
		while (true) 
		{
			plog('Starting: ' . $rChannel);
			plog('Killing directory if exists.');
			exec('rm -rf ' . MAIN_DIR . 'video/' . $rChannel);
			exec('rm -rf ' . MAIN_DIR . 'hls/' . $rChannel);
			$rDatabase = setCache($rDatabase, 'php_pid', getmypid());
			saveCache($rChannel, $rDatabase);
			plog('Creando directorios para el canal '.$rChannel);
			mkdir(MAIN_DIR . 'video/' . $rChannel);
			mkdir(MAIN_DIR . 'video/' . $rChannel . '/aria');
			mkdir(MAIN_DIR . 'video/' . $rChannel . '/decrypted');
			mkdir(MAIN_DIR . 'video/' . $rChannel . '/encrypted');
			mkdir(MAIN_DIR . 'video/' . $rChannel . '/final');
			mkdir(MAIN_DIR . 'hls/' . $rChannel);
			mkdir(MAIN_DIR . 'hls/' . $rChannel . '/hls');
			plog('Obteniendo el manifiesto para el canal '.$rChannel);
			$rChannelData = getMovistarChannel($rChannel);

			if ($rChannelData) 
			{
				$rStarted = false;
				$rMemoryUsage = 0;
				$rFFPID = NULL;
				$rStreamInfo = NULL;

				while (true) 
				{
					$rMemoryUsage = memory_get_usage();
					plog('Memory usage: ' . round($rMemoryUsage / 1024 / 1024, 2) . ' MB');
					plog('DRM Processes: ' . getProcessCount());
					$rKeyFail = false;
					$rStart = round(microtime(true) * 1000);

					if (!is_dir(MAIN_DIR . 'video/' . $rChannel . '/final')) {
						plog('Detención forzada');
						break;
					}

					plog('Obteniendo segmentos...');
					
					$rSegments = getServiceSegmentsMovistar($rChannelData, 3, $rScript, $rLanguage);
			                
					if ($rSegments && strlen($rSegments['video'])) 
					{
						$rKeys = getKeyCache(md5($rChannel));

						if (!$rKeys) 
						{
							foreach (range(1, 3) as $rRetry) {
								plog('Solicitando key para el canal : ' . $rChannel);
								$rData = getKey($rScript, $rChannel);

								if ($rData['status']) {
									
									$rKeys = $rData['key'];
                                                                        plog('¡Keys obtenidas!');
									if (!setKeyCache(md5($rChannel), $rData['key'])) 
									{
										plog('[FATAL] No se puede escribir en la cache de keys. Cerrando el servidor para conservar la integridad.');
										exit();
									}

									break;
								}
								else 
									plog('[ERROR] Error al obtener la key, reintentando');
								unset($rRetry, $rData);
							}
						}
						else 
							plog('Key del canal obtenida');
						if ($rKeys) 
						{   
						        plog('Procesando '.count($rSegments['segments']).' segmentos');
							$rCompleted = processSegments($rKeys, $rSegments, $rVideoDir . '/' . $rChannel, 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.63 Safari/537.36', $rScript); 
							if ($rCompleted[1]) // Se procesó algun segmento nuevo
							{
								plog('Finished processing segments.');

								if ($rCompleted[1] != $rCompleted[0]) {
									plog('Saltando periodo de espera.');
									$rStart = 0;
								}

								file_put_contents(MAIN_DIR . 'video/' . $rChannel . '/.update', '1');
								plog('Creando lista de segmentos en '.$rVideoDir . '/' . $rChannel.' ADD='.$rSegments['add']);
								updateSegments($rVideoDir . '/' . $rChannel, 3, false, 21600, $rSegments['add']);
								
								if (!$rFFPID || !file_exists('/proc/' . $rFFPID) || (file_exists(MAIN_DIR . ('hls/' . $rChannel . '/hls/playlist.m3u8')) && (60 <= time() - filemtime(MAIN_DIR . ('hls/' . $rChannel . '/hls/playlist.m3u8'))))) 
								{
									if (file_exists(MAIN_DIR . 'video/' . $rChannel . '/.ffmpeg')) {
										plog('[ERROR] Fallo de ffmpeg! Incrementa el limite de fallo');
									}

									plog('Empezando con la lista ffmpeg.');
									exec('rm -f ' . MAIN_DIR . 'hls/' . $rChannel . '/hls/*');
									file_put_contents(MAIN_DIR . 'video/' . $rChannel . '/.ffmpeg', '1');

									if ($rFFPID) 
									    exec('kill -9 ' . $rFFPID);
									

									$rFFPID = startPlaylistMovistar($rChannel);
									$rDatabase = setCache($rDatabase, 'ffmpeg_pid', $rFFPID);
									$rStarted = true;
								}
								else if (!$rStreamInfo || (strlen($rStreamInfo) == 0)) {
									plog('Obteniendo informacion del stream');
									$rStreamInfo = getStreamInfo($rChannel);

									if (128 < strlen($rStreamInfo)) {
										$rDatabase = setCache($rDatabase, 'stream_info', $rStreamInfo);
									}
								}

								plog('Limpiando segmentos antiguos');
								clearSegments($rChannel);
								plog('Copiando la base de datos a un fichero');
								saveCache($rChannel, $rDatabase);
								print("\n");
							}
							else {
								plog('[FATAL] No hay segmentos, reiniciamos');
								break;
							}
						}
						else 
							$rKeyFail = true;
					}
					else 
						plog('[FATAL] No pudieron obtenerse los segmentos de '.$rChannelData);
					

					unset($rSegments, $rKeys);

					if ($rKeyFail) {
						plog('[FATAL] Fallo al obtener la key, esperando');
						sleep($rKeyWait);
					}
					else if (!$rStarted) {
						plog('[ERROR] Fallo en el primer arranque. Esperamos mas de lo usual');
						sleep($rFailWait);
					}
					else {
						$rFinish = round(microtime(true) * 1000);
						$rTaken = $rFinish - $rStart;
                                                #plog('tomado: '.$rTaken.' finish='.$rFinish.' start='.$rStart);
						if ($rTaken < $rWait) 
							usleep(($rWait - $rTaken) * 1000);
						unset($rFinish, $rTaken);
					}

					unset($rStart, $rKeyFail);
					sleep(3);
					gc_collect_cycles();
				}
			}
			else {
				plog('[FATAL] Fallo al obtener la url del canal '.$rChannel);
				sleep($rFailWait);
			}

			plog('Terminado, limpiando');
			if ($rFFPID && file_exists('/proc/' . $rFFPID)) 
				exec('kill -9 ' . $rFFPID);

			unset($rMPD, $rStarted, $rFFPID, $rStreamInfo, $rMemoryUsage);
		}

		unset($rDatabase, $rWait, $rFailWait, $rKeyWait, $rChannel);
		plog('¡Adios!');
		gc_disable();
		exit();
	}
}
else if (isset($argv[1]) && (strtoupper($argv[1]) == 'STOP') && isset($argv[2])) {
	$rChannel = $argv[2];
	$rPID = getCache($rDatabase, 'ffmpeg_pid');

	if ($rPID) {
		exec('kill -9 ' . $rPID);
	}

	$rPID = getCache($rDatabase, 'php_pid');
	exec('kill -9 `ps -ef | grep \'DRM_' . $rChannel . '\' | grep -v grep | awk \'{print $2}\'`');
	exec('rm -rf ' . MAIN_DIR . 'video/' . $rChannel);
	exec('rm -rf ' . MAIN_DIR . 'hls/' . $rChannel);
	deleteCache($rChannel);
	unset($rChannel, $rPID);
}
?>