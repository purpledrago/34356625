<?php
function getURL($rURL, $rTimeout = 5)
{
    $rUA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.63 Safari/537.36';
	$rContext = stream_context_create([
		'http' => ['method' => 'GET', 'timeout' => $rTimeout, 'header' => 'User-Agent: ' . $rUA . "\r\n"],
		'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
	]);
	return file_get_contents($rURL, false, $rContext);
}

function getMovistarChannel($rChannel)
{
	$json =json_decode(getURL('/home/wvtohls/origin/movistar.json'), true);
	return $json[$rChannel]['url'];
}

function getKeyCache($rKey)
{
	if (file_exists(MAIN_DIR . 'cache/keystore/' . $rKey . '.key')) {
		return decryptkey(file_get_contents(MAIN_DIR . 'cache/keystore/' . $rKey . '.key'));
	}

	return NULL;
}

function getURLBase($rURL)
{
	preg_match('/(?:[^\/]*+\/)++/', $rURL, $matches);
	return $matches[0];
}

function encryptKey($rKey)
{
	global $rAESKey;
	$method = 'AES-256-CBC';
	$key = hash('sha256', $rAESKey, true);
	$iv = openssl_random_pseudo_bytes(16);
	$ciphertext = openssl_encrypt($rKey, $method, $key, OPENSSL_RAW_DATA, $iv);
	$hash = hash_hmac('sha256', $ciphertext . $iv, $key, true);
	return $iv . $hash . $ciphertext;
}

function decryptKey($rKey)
{
	global $rAESKey;
	$method = 'AES-256-CBC';
	$iv = substr($rKey, 0, 16);
	$hash = substr($rKey, 16, 32);
	$ciphertext = substr($rKey, 48);
	$key = hash('sha256', $rAESKey, true);

	if (!hash_equals(hash_hmac('sha256', $ciphertext . $iv, $key, true), $hash)) {
		return NULL;
	}

	return openssl_decrypt($ciphertext, $method, $key, OPENSSL_RAW_DATA, $iv);
}

function plog($rText)
{
	echo '[' . date('Y-m-d hrKeys:i:s') . '] ' . $rText . "\n";
}

function getBaseUrlMovistar($url)
{
   $keep=True;
   do
   {
      $info=get_headers($url,1);
      if (is_array($info) and array_key_exists('Location',$info) and strlen(end($info['Location'])))
         $url=end($info['Location']);
      else
         $keep=False;
   } while($keep);
   preg_match('/(http.+?\/)manifest.mpd/', $url, $base);
   $base = $base[1];
   return $base;
}

function updateSegments($rDirectory, $rSampleSize = 10, $rHex = true, $rSize = 43200, $rMultiplier = 1)
{
	$rFiles = glob($rDirectory . '/final/*.mp4');
	usort($rFiles, function($a, $b) {
		return filemtime($a) - filemtime($b);
	});
	$rFiles = array_slice($rFiles, -1 * $rSampleSize, $rSampleSize, true);
	$rMin = NULL;
	$rMax = NULL;
        // Se busca el id del segmento mas nuevo
	foreach ($rFiles as $rFile) 
	{
	        $rInt = intval(explode('.', basename($rFile))[0]);
		if ($rHex) 
			$rInt = intval(hexdec(explode('.', basename($rFile))[0]));
		if (!$rMin || ($rInt < $rMin)) 
			$rMin = $rInt;
		if (!$rMax || ($rMax < $rInt)) 
			$rMax = $rInt;
		
	}

	if ($rMin) 
	{
		$rOutput = '';

		foreach (range(0, $rSize) as $rAdd) {
		        $rPath = $rDirectory . '/final/' . ($rMin + ($rAdd * $rMultiplier)) . '.mp4';
			if ($rHex) 
				$rPath = $rDirectory . '/final/' . dechex($rMin + ($rAdd * $rMultiplier)) . '.mp4';
			
			if (file_exists($rPath) || ($rMax < ($rMin + ($rAdd * $rMultiplier)))) 
				$rOutput .= 'file \'' . $rPath . '\'' . PHP_EOL;
		}

		file_put_contents($rDirectory . '/playlist.txt', $rOutput);
	}
}

function downloadFiles($rList, $rOutput, $rUA = NULL)
{
	global $rAria;
	$rTimeout = count($rList);

	if ($rTimeout < 3) 
           $rTimeout = 12;

	if (0 < count($rList)) 
	{
		$rURLs = join("\n", $rList);
		$rTempList = MAIN_DIR . 'tmp/' . md5($rURLs) . '.txt';
		file_put_contents($rTempList, $rURLs);

		if ($rUA) 
			exec($rAria . ' -U "' . $rUA . '" --connect-timeout=3 --timeout=' . $rTimeout . ' -i "' . $rTempList . '" --dir "' . $rOutput . '" 2>&1', $rOut, $rRet);
		else 
			exec($rAria . ' --connect-timeout=3 --timeout=' . $rTimeout . ' -i "' . $rTempList . '" --dir "' . $rOutput . '" 2>&1', $rOut, $rRet);
		unlink($rTempList);
	}

	return true;
}

function decryptSegment($rKey, $rInput, $rOutput, $rServiceName)
{
	global $rMP4Decrypt;
	$rKeyN = explode(':', $rKey);
	switch($rServiceName){

		case "movistar":
		   $rVideoChannel = $rKeyN[0];
		   $rAudioChannel = $rKeyN[0];
		break;
	}

	if (count($rKeyN) == 2) {
		
		if (0<strpos($rInput, 'video.complete.m4s')) 
		{
			$rWait = exec($rMP4Decrypt . ' --key '.$rVideoChannel.':' . $rKeyN[1] . ' ' . $rInput . ' ' . $rOutput . ' 2>&1 &');
		        plog('Desencriptando segmento de video: ' . $rInput.' en '. $rOutput);
                }
                else
		if (0<strpos($rInput, 'audio.complete.m4s')) 
		{
			$rWait = exec($rMP4Decrypt . ' --key '.$rAudioChannel.':' . $rKeyN[1] . ' ' . $rInput . ' ' . $rOutput . ' 2>&1 &');
			plog('Desencriptando segmento de audio: ' . $rInput.' en '. $rOutput);
		}
	}else if (count($rKeyN) == 4){

		if (0<strpos($rInput, 'video')) 
			$rWait = exec($rMP4Decrypt . ' --key '.$rVideoChannel.':' . $rKeyN[1] . ' ' . $rInput . ' ' . $rOutput . ' 2>&1 &');
		

		if (0<strpos($rInput, 'audio')) 
			$rWait = exec($rMP4Decrypt . ' --key '.$rAudioChannel.':' . $rKeyN[3] . ' ' . $rInput . ' ' . $rOutput . ' 2>&1 &');
		

	}else {
		$rWait = exec($rMP4Decrypt . ' --key 1:' . $rKeyN[1] . ' --key 2:' . $rKey[1] . ' ' . $rInput . ' ' . $rOutput . ' 2>&1 &');
	}

	return file_exists($rOutput);
}
function processSegments($rKey, $rSegments, $rDirectory, $rUA = NULL, $rServiceName)
{
        
	$rCompleted = 23;
	if (!is_file($rDirectory . '/encrypted/init.audio.mp4') || (filesize($rDirectory . '/encrypted/init.audio.mp4') == 0)) 
		downloadfile($rSegments['audio'], $rDirectory . '/encrypted/init.audio.mp4', true);
	
	if (!is_file($rDirectory . '/encrypted/init.video.mp4') || (filesize($rDirectory . '/encrypted/init.video.mp4') == 0)) 
		downloadfile($rSegments['video'], $rDirectory . '/encrypted/init.video.mp4', true);
	
        #plog('el init es '.$rSegments['video']);
        
	$rDownloadPath = $rDirectory . '/aria/';
 
        // Descarga los segmentos de audio y los de video en el directorio final del canal
	foreach (['audio', 'video'] as $rType) 
	{
		$rDownloads = [];    // Lista de URL a descargar, de audio o video
		$rDownloadMap = [];  // Pares de claves valor formadas por la url y el id que lo representa

		foreach ($rSegments['segments'] as $rSegmentID => $rSegment) 
		{
			$rFinalPath = $rDirectory . '/final/' . $rSegmentID . '.mp4';
                        // Solo queremos descargar los segmentos que no esten ya procesados 
			if (!is_file($rFinalPath)) 
			{
				$rDownloads[] = $rSegment[$rType];
				$rDownloadMap[$rSegment[$rType]] = $rSegmentID;
			}
		}
		downloadfiles($rDownloads, $rDownloadPath, $rUA);
		foreach ($rDownloads as $rURL) 
		{
			$rBaseName = parse_url(basename($rURL),PHP_URL_PATH);  // Nombre del fichero con el segmento en el servidor de origen
			#plog($rBaseName);
			$rMap = $rDownloadMap[$rURL];
			$rPath = $rDownloadPath . $rBaseName;
			if (is_file($rPath) && (0 < filesize($rPath))) 
			{
				#plog($rPath);
				#plog('Descargado segmento en '.$rDirectory . '/encrypted/' . $rMap . '.' . $rType . '.m4s');
				rename($rPath, $rDirectory . '/encrypted/' . $rMap . '.' . $rType . '.m4s');
			}
		}
	}

	foreach ($rSegments['segments'] as $rSegmentID => $rSegment) 
	{
		$rFinalPath = $rDirectory . '/final/' . $rSegmentID . '.mp4';

		if (!is_file($rFinalPath)) 
		{
			// Desencriptamos los segmentos de video
			if (is_file($rDirectory . '/encrypted/' . $rSegmentID . '.video.m4s')) 
			{
				exec('cat "' . $rDirectory . '/encrypted/init.video.mp4" "' . $rDirectory . '/encrypted/' . $rSegmentID . '.video.m4s" > "' . $rDirectory . '/encrypted/' . $rSegmentID . '.video.complete.m4s"');
				$rVideoPath = $rDirectory . '/decrypted/' . $rSegmentID . '.video.mp4';
                                plog('vamos a desencriptar '.$rDirectory . '/encrypted/' . $rSegmentID . '.video.complete.m4s');
				if (!decryptsegment($rKey, $rDirectory . '/encrypted/' . $rSegmentID . '.video.complete.m4s', $rVideoPath, $rServiceName)) 
					plog('[ERROR] Fallo al desencriptar '. $rVideoPath);
			}
			else 
				plog('[ERROR] No hay lista de segmentos de video para combinar');
                        
                        // Desencriptamos los segmentos de audio
			if (is_file($rDirectory . '/encrypted/' . $rSegmentID . '.audio.m4s')) 
			{
				exec('cat "' . $rDirectory . '/encrypted/init.audio.mp4" "' . $rDirectory . '/encrypted/' . $rSegmentID . '.audio.m4s" > "' . $rDirectory . '/encrypted/' . $rSegmentID . '.audio.complete.m4s"');
				$rAudioPath = $rDirectory . '/decrypted/' . $rSegmentID . '.audio.mp4';
                                
                                $rAudioKey = $rKey;
                                // Por defecto se supone que la key de audio es la ultima de las obtenidas
				if (is_array($rKey)) 
					$rAudioKey = end($rKey);
				plog('vamos a desencriptar '.$rDirectory . '/encrypted/' . $rSegmentID . '.audio.complete.m4s');
				if (!decryptsegment($rAudioKey, $rDirectory . '/encrypted/' . $rSegmentID . '.audio.complete.m4s', $rAudioPath, $rServiceName))
					plog('[ERROR] Error al desencriptar segmento de audio!');
			}
			else
				plog('[ERROR] No hay lista de segmentos de audio para combinar');
			
			if (is_file($rVideoPath) && is_file($rAudioPath)) {
				if(combinesegment($rVideoPath, $rAudioPath, $rFinalPath))
				   plog('Combinados los segmentos '.$rVideoPath.' y '.$rAudioPath.' en '.$rFinalPath);
				else
				   plog('Algun error sucedió al combinar los segmentos '.$rVideoPath.' y '.$rAudioPath.' en '.$rFinalPath);
			}
			else 
				plog('[ERROR] No hay segmentos que combinar!');
			
			unlink($rDirectory . '/encrypted/' . $rSegmentID . '.video.m4s');
			unlink($rDirectory . '/encrypted/' . $rSegmentID . '.audio.m4s');
			unlink($rDirectory . '/encrypted/' . $rSegmentID . '.video.complete.m4s');
			unlink($rDirectory . '/encrypted/' . $rSegmentID . '.audio.complete.m4s');
			unlink($rVideoPath);
			unlink($rAudioPath);

			if (is_file($rFinalPath)) 
				$rCompleted++;
		}
	}

	return [count($rDownloads), $rCompleted];
}

function getServiceSegmentsMovistar($rChannelData, $rLimit = NULL, $rServiceName, $rLang)
{
	global $rMaxSegments;
	global $rMP4dump;
	if (!file_exists(MAIN_DIR . Init . $rServiceName))
		mkdir(MAIN_DIR . Init . $rServiceName, 493, true);

	foreach (range(1, 1) as $rRetry) 
	{
		$rUA = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/93.0.4577.63 Safari/537.36';
		$rOptions = [
			'http' => ['method' => 'GET', 'header' => 'User-Agent: ' . $rUA . "\r\n"],
			'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
		];
		$rContext = stream_context_create($rOptions);
		$rData = file_get_contents($rChannelData, false, $rContext);
  
		if (strpos($rData, '<MPD')) 
		{
			$rMPD = simplexml_load_string($rData);
			
			/*
			$pathBase = $rMPD->Period->BaseURL;
			if($pathBase)
			{
				preg_match('/(?:\w\w\w\w)++\d\w\+[^<]++/', $pathBase, $matches);
				if(!$matches)
				{
					$baseurl = getURLBase($rChannelData).$pathBase;
				}
			}else{
					$baseurl = getURLBase($rChannelData);
			}
			*/
			$rBaseURL = getBaseUrlMovistar($rChannelData);
			$rVideoStart = NULL;
			$rAudioStart = NULL;
			$rVideoTemplate = NULL;
			$rIndex = [];       // Tiempos de inicio de cada uno de los segmentos descritos en el manifiesto
			$rPSSH = NULL;
			$rSegmentStart = 0;
                        
			//gets pssh from first Period inside manifest
			foreach ($rMPD->Period->AdaptationSet as $rAdaptationSet) 
			{
					if ($rAdaptationSet->attributes()['mimeType'] == 'video/mp4' OR $rAdaptationSet->attributes()['contentType'] == 'video') 
					{
						$rID = $rAdaptationSet->Representation[0]->attributes()['id'];
						$rVideoTemplate = str_replace('$RepresentationID$', $rID, $rAdaptationSet->SegmentTemplate[0]->attributes()['media']);
						$rInitSegment = str_replace('$RepresentationID$', $rID, $rBaseURL . $rAdaptationSet->SegmentTemplate[0]->attributes()['initialization']);
						foreach($rMPD->Period->AdaptationSet[0]->ContentProtection as $rContentProtection)
						{
							if($rContentProtection->attributes()['schemeIdUri'] == 'urn:uuid:edef8ba9-79d6-4ace-a3c8-27dcd51d21ed' OR $rContentProtection->attributes()['schemeIdUri'] == 'urn:uuid:EDEF8BA9-79D6-4ACE-A3C8-27DCD51D21ED')
							{
								preg_match('/(?:\w\w\w\w)++\d\w\+[^<]++/', $rData, $matches);
								if($matches)
								{
									$rPSSH  = $matches[0];
									plog('PSSH: ' . $rPSSH);
									
								}
							}
						}
						if(!$rPSSH)
						{
							file_put_contents(MAIN_DIR . Init . $rServiceName . '/' . md5($rInitSegment), file_get_contents($rInitSegment, false, $rContext));
							$rPSSH_res = shell_exec($rMP4dump . ' --verbosity 3 --format json ' . MAIN_DIR . Init . $rServiceName . '/' . md5($rInitSegment));
							preg_match('#"data":"\\[(.+?)\\]#', $rPSSH_res, $rPSSH);
							plog('PSSH: ' . $rPSSH);
						}
						break;
					}
			}

			$rObject = [
					'pssh'     => $rPSSH,
					'audio'    => NULL,
					'video'    => NULL,
					'segments' => [],
					'add'      => 100
				];

			$maxwidth=-1;
			$AdaptationIndex=-1;
			$i=0;

			// Buscando la calidad de video más alta
			$Period=$rMPD->Period[0];

			      foreach ($Period->AdaptationSet as $rAdaptationSet)
			          if ($rAdaptationSet->attributes()['mimeType'] == 'video/mp4' OR $rAdaptationSet->attributes()['contentType'] == 'video')
			          {
			              if($rAdaptationSet->attributes()['maxWidth']> $maxwidth)
			              {
			                 $maxwidth=$rAdaptationSet->attributes()['maxWidth'];
			                 $AdaptationIndex=$i;
			              }   
			              $i++;
			          }
			      
			#foreach($rMPD->Period as $id => $Period)
			{
			//Loop through all video $Time segments and creates a column vector in $rIndex
			      #foreach ($Period->AdaptationSet as $rAdaptationSet) 
			      $rAdaptationSet=$Period->AdaptationSet[$AdaptationIndex];
			      {
					if ($rAdaptationSet->attributes()['mimeType'] == 'video/mp4' OR $rAdaptationSet->attributes()['contentType'] == 'video') 
					{
						$rID = $rAdaptationSet->Representation[0]->attributes()['id'];
						$rVideoTemplate = str_replace('$RepresentationID$', $rID, $rAdaptationSet->SegmentTemplate[0]->attributes()['media']);
						$rInitSegment = str_replace('$RepresentationID$', $rID, $rBaseURL . $rAdaptationSet->SegmentTemplate[0]->attributes()['initialization']);
						$rObject['video'] = $rInitSegment;
						foreach ($rAdaptationSet->SegmentTemplate->SegmentTimeline->S as $rSegment) 
						{
							if (isset($rSegment->attributes()['t'])) 
							{
								$rVideoStart = $rSegment->attributes()['t'];
								
							}
							if (isset($rSegment->attributes()['d'])) 
							   $rObject['add'] = $rSegment->attributes()['d'];
							$rRepeats = 1;
							if (isset($rSegment->attributes()['r'])) 
							   $rRepeats = intval($rSegment->attributes()['r']) + 1;
							foreach (range(1, $rRepeats) as $rRepeat) 
							{
								$rVideoStart += intval($rSegment->attributes()['d']);
								array_push($rIndex, $rVideoStart);
								$rObject['segments'][$rVideoStart]['video'] = str_replace('$Time$', $rVideoStart, $rBaseURL . $rVideoTemplate);
							}
						}
					}
					
				}
			}
			
			//loops through all Periods inside manifest
			//Populates audio segments in order based on column vector previously created
			foreach($rMPD->Period as $id => $Period)
			{
				foreach ($Period->AdaptationSet as $rAdaptationSet) 
				{
					if ( ($rAdaptationSet->attributes()['mimeType'] == 'audio/mp4' AND $rAdaptationSet->attributes()['lang'] == $rLang)  OR 
					     ($rAdaptationSet->attributes()['mimeType'] == 'audio/mp4' AND !isset($rAdaptationSet->attributes()['lang']))    OR 
					     ($rAdaptationSet->attributes()['contentType'] == 'audio'  AND $rAdaptationSet->attributes()['lang'] == $rLang)  OR
					     ($rAdaptationSet->attributes()['mimeType'] == 'audio/mp4' AND !isset($rAdaptationSet->attributes()['lang']))
					   )  
				        {
						$rID = $rAdaptationSet->Representation[count($rAdaptationSet->Representation) - 1]->attributes()['id'];
						$rAudioTemplate = str_replace('$RepresentationID$', $rID, $rAdaptationSet->SegmentTemplate[0]->attributes()['media']);
						$rInitSegment = str_replace('$RepresentationID$', $rID, $rBaseURL . $rAdaptationSet->SegmentTemplate[0]->attributes()['initialization']);
						$rObject['audio'] = $rInitSegment;
						foreach ($rAdaptationSet->SegmentTemplate->SegmentTimeline->S as $rSegment) 
						{
							if (isset($rSegment->attributes()['t'])) {
								$rAudioStart = $rSegment->attributes()['t'];
								
							}

							$rRepeats = 1;
							if (isset($rSegment->attributes()['r'])) 
								$rRepeats = intval($rSegment->attributes()['r']) + 1;

							foreach (range(1, $rRepeats) as $rRepeat) 
							{
								$rAudioIndex = $rIndex[$rSegmentStart];
								$rAudioStart += intval($rSegment->attributes()['d']);
								$rObject['segments'][$rAudioIndex]['audio'] = str_replace('$Time$', $rAudioStart, $rBaseURL . $rAudioTemplate);
								$rSegmentStart++;
							}
						}

					}
				}
			}


			$rSegmentsCount = count($rObject['segments']);
			plog('Segmentos totales del manifiesto: '.$rSegmentsCount);
			#$rObject['segments'] = array_slice($rObject['segments'], -1*$rLimit, $rLimit, true);
			#$rObject['segments'] = array_slice($rObject['segments'], 15, $rLimit, true);
			$rObject['segments'] = array_slice($rObject['segments'], $rSegmentsCount -3*$rLimit, $rLimit, true);
			return $rObject;
		}
	}
}

function getProcessCount()
{
	exec('pgrep -u wvtohls | wc -l 2>&1', $rOutput, $rRet);
	return intval($rOutput[0]);
}

function setKeyCache($rKey, $rValue)
{
	file_put_contents(MAIN_DIR . 'cache/keystore/' . $rKey . '.key', encryptkey($rValue));

	if (file_exists(MAIN_DIR . 'cache/keystore/' . $rKey . '.key')) {
		return true;
	}

	return false;
}

function openCache($rChannel)
{
	if (file_exists(MAIN_DIR . 'cache/' . $rChannel . '.db')) {
		return json_decode(file_get_contents(MAIN_DIR . 'cache/' . $rChannel . '.db'), true);
	}

	return [];
}

function deleteCache($rChannel)
{
	if (file_exists(MAIN_DIR . 'cache/' . $rChannel . '.db')) {
		unlink(MAIN_DIR . 'cache/' . $rChannel . '.db');
	}

	return [];
}

function clearCache($rDatabase, $rID)
{
	unset($rDatabase[$rID]);
	return $rDatabase;
}

function getCache($rDatabase, $rID)
{
	if (isset($rDatabase[$rID])) {
		return $rDatabase[$rID]['value'];
	}

	return NULL;
}

function setCache($rDatabase, $rID, $rValue)
{
	global $rCacheTime;
	$rDatabase[$rID] = ['value' => $rValue, 'expires' => time() + $rCacheTime];
	return $rDatabase;
}

function saveCache($rChannel, $rDatabase)
{
	file_put_contents(MAIN_DIR . 'cache/' . $rChannel . '.db', json_encode($rDatabase));
}

function getPersistence()
{
	if (file_exists(MAIN_DIR . 'config/persistence.db')) {
		$rPersistence = json_decode(file_get_contents(MAIN_DIR . 'config/persistence.db'), true);
	}
	else {
		$rPersistence = [];
	}

	return $rPersistence;
}

function addPersistence($rScript, $rChannel)
{
	$rPersistence = getpersistence();

	if (!in_array($rChannel, $rPersistence[$rScript])) {
		$rPersistence[$rScript][] = $rChannel;
	}

	file_put_contents(MAIN_DIR . 'config/persistence.db', json_encode($rPersistence));
}

function removePersistence($rScript, $rChannel)
{
	$rPersistence = getpersistence();

	if (($rKey = array_search($rChannel, $rPersistence[$rScript])) !== false) {
		unset($rPersistence[$rScript][$rKey]);
	}

	file_put_contents(MAIN_DIR . 'config/persistence.db', json_encode($rPersistence));
}

function getKey($rType, $rChannel)
{
	$res=array();
	if (file_exists('/home/wvtohls/origin/'.$rType.'.json')) 
	{
	   $json=file_get_contents('/home/wvtohls/origin/'.$rType.'.json');
	   $json=json_decode($json,1);
           $res['status']='OK';
           $res['key']=$json[$rChannel]['key'];
	}
	return $res;
}

function combineSegment($rVideo, $rAudio, $rOutput)
{
	global $rFFMpeg;
	#$rWait = exec($rFFMpeg . ' -hide_banner -loglevel panic -y -nostdin -i "' . $rVideo . '" -i "' . $rAudio . '" -c:v copy -c:a copy -strict experimental "' . $rOutput . '" ');
	$rWait = exec($rFFMpeg . ' -hide_banner -loglevel panic -y -nostdin -i "' . $rVideo . '" -i "' . $rAudio . '" -c copy "' . $rOutput . '" ');
	return file_exists($rOutput);
}



// Clear segments from final directory
function clearSegments($rChannel, $rLimit = NULL)
{
	global $rMaxSegments;
	global $rVideoDir;

	if (!$rLimit) 
		$rLimit = $rMaxSegments;
	
	$rFiles = glob($rVideoDir . '/' . $rChannel . '/final/*.mp4');
	usort($rFiles, function($a, $b) {
		return filemtime($a) - filemtime($b);
	});
	$rKeep = array_slice($rFiles, -1 * $rLimit, $rLimit, true);

	foreach ($rFiles as $rFile) {
		if (!in_array($rFile, $rKeep)) {
			unlink($rFile);
		}
	}
}

function clearMD5Cache($rChannel, $rLimit = 60)
{
	global $rVideoDir;
	$rFiles = glob($rVideoDir . '/' . $rChannel . '/cache/*.md5');
	usort($rFiles, function($a, $b) {
		return filemtime($a) - filemtime($b);
	});
	$rKeep = array_slice($rFiles, -1 * $rLimit, $rLimit, true);

	foreach ($rFiles as $rFile) {
		if (!in_array($rFile, $rKeep)) {
			unlink($rFile);
		}
	}
}



function downloadFile($rInput, $rOutput, $rPHP = false)
{
		$rUA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36';
		$rOptions = [
			'http' => ['method' => 'GET', 'header' => 'User-Agent: ' . $rUA . "\r\n"],
			'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
		];
		$rContext = stream_context_create($rOptions);
	if ($rPHP) {
		file_put_contents($rOutput, file_get_contents($rInput, false, $rContext));
	}
	else {
		$rWait = exec('curl "' . $rInput . '" --output "' . $rOutput . '"');
	}
	if (file_exists($rOutput) && (0 < filesize($rOutput))) {
		return true;
	}

	return false;
}



function downloadFilesWriteJson($rList, $rOutput, $rUA = NULL)
{
	global $rAria;
	$rTimeout = count($rList);

	if ($rTimeout < 3) {
		$rTimeout = 12;
	}

	if (0 < count($rList)) {
		$rURLs = join("\n", $rList);
		$rTempList = MAIN_DIR . 'tmp/' . md5($rURLs) . '.txt';
		file_put_contents($rTempList, $rURLs);

		if ($rUA) {
			exec($rAria . ' -U "' . $rUA . '" --connect-timeout=3 --timeout=' . $rTimeout . ' -i "' . $rTempList . '" --dir "' . $rOutput . '" 2>&1', $rOut, $rRet);
		}
		else {
			exec($rAria . ' --connect-timeout=3 --timeout=' . $rTimeout . ' -i "' . $rTempList . '" --dir "' . $rOutput . '" 2>&1', $rOut, $rRet);
		}

		unlink($rTempList);
	}

	return true;
}

function startPlaylistMovistar($rChannel)
{
	global $rFFMpeg;
	$rPlaylist = MAIN_DIR . 'video/' . $rChannel . '/playlist.txt';

	if (file_exists($rPlaylist)) {
		$rOutput = MAIN_DIR . 'hls/' . $rChannel . '/hls/playlist.m3u8';
		$rFormat = MAIN_DIR . 'hls/' . $rChannel . '/hls/segment%d.ts';

		if (!file_exists($rOutput)) {
			$rTime = time();
			$log = MAIN_DIR . 'logs/ffmpeg/' . $rChannel . '.log';
			$old_log =  MAIN_DIR . 'logs/ffmpeg/' . $rChannel . '_' . $rTime . '.log';
			  if(file_exists($old_log)){
			    #exec('mv '. $log .' '. $old_log);
			    exec('rm '. $log .' '. $old_log);
			  }
                       $rPID = exec($rFFMpeg . ' -y -nostdin -hide_banner -err_detect ignore_err -nofix_dts -start_at_zero -copyts -vsync 0 -correct_ts_overflow 0 -avoid_negative_ts disabled -max_interleave_delta 0 -re -probesize 15000000 -analyzeduration 15000000 -safe 0 -f concat -i ' . $rPlaylist . ' -strict -2 -dn -acodec copy -vcodec copy -hls_flags delete_segments -hls_time 4 -hls_list_size 10 '.$rOutput.' > ' . $log . ' 2>&1 & echo $!;', $rScriptOut);
			return $rPID;
		}
	}
}

function getStreamInfo($rID)
{
	global $rFFProbe;
	list($rPlaylist) = array_slice(glob(MAIN_DIR . 'hls/' . $rID . '/hls/*.ts'), -1);
	$rOutput = '';

	if (file_exists($rPlaylist)) {
		exec($rFFProbe . ' -v quiet -print_format json -show_streams -show_format "' . $rPlaylist . '" 2>&1', $rOutput, $rRet);
	}

	return json_encode(json_decode(join("\n", $rOutput), true));
}

function getMissingSegments($rID, $rMax, $rLimit)
{
	$rReturn = [];

	if (0 < $rMax) {
		$rMin = ($rMax - $rLimit) + 1;

		if ($rMin <= 0) {
			$rMin = 12;
		}

		$rSegments = [];

		foreach (glob(MAIN_DIR . 'video/' . $rID . '/final/*.mp4') as $rFile) {
			$rSegments[] = intval(hexdec(explode('.', basename($rFile))[0]));
		}

		foreach (range($rMin, $rMax) as $rInt) {
			if (!in_array($rInt, $rSegments)) {
				$rReturn[] = dechex($rInt);
			}
		}
	}

	return $rReturn;
}



define('MAIN_DIR', '/home/wvtohls/');
define('Init', 'logs/');
require MAIN_DIR . 'config/config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(5);
$rMaxSegments = 32;
$rCacheTime = 21604;
$rDSTVLimit = 7;
$rVideoDir = MAIN_DIR . 'video';
$rHLSDir = MAIN_DIR . 'hls';
$rMP4Decrypt = MAIN_DIR . 'bin/mp4decrypt';
$rFFMpeg = MAIN_DIR . 'bin/ffmpeg';
$rFFProbe = MAIN_DIR . 'bin/ffprobe';
$rMP4dump = MAIN_DIR . 'bin/mp4dump';
$rAria = '/usr/bin/aria2c';
$days = 1;
$path = MAIN_DIR . 'cache/keystore/';
$rAESKey = '7621a37df31ee733b01761187639d7816a7fb475a425695e5449bb1cfed2e091';

if ($handle = opendir($path)) {
	while (false !== $file = readdir($handle)) {
		if (is_file($path . $file)) {
			if (filemtime($path . $file) < (time() - ($days * 24 * 60 * 60))) {
				unlink($path . $file);
			}
		}
	}
}
?>
