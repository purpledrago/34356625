<?php
set_time_limit(0);
$db = json_decode(file_get_contents('monitor.db'), true);
$pid=-1;
foreach($db as $servicename => $service)
{
   foreach($service as $id => $channel_id)
   {
      $playlist = '/home/wvtohls/hls/'.$channel_id.'/hls/playlist.m3u8';
      $file_db = '/home/wvtohls/cache/'.$channel_id.'.db';
      if (file_exists($file_db))
      {
         $channel_db = json_decode(file_get_contents($file_db), true);
         if (array_key_exists('ffmpeg_pid',$channel_db))
            $pid = $channel_db['ffmpeg_pid']['value'];
      }
      if(!file_exists('/proc/'.$pid))
      {
         file_get_contents('http://localhost:18001/api.php?id='.$channel_id.'&service='.$servicename.'&action=stop');
         file_get_contents('http://localhost:18001/api.php?id='.$channel_id.'&service='.$servicename.'&action=start');
         echo "CANAL ".$channel_id." INICIADO".PHP_EOL;
      }
      else
         echo "EL CANAL ".$channel_id." YA ESTABA FUNCIONANDO".PHP_EOL;
   }
}
