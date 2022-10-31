<?php

include '../includes/functions.php';
$rAction = strtoupper($_GET['action']);

if (isset($_GET['id'])) {
 $rChannel = $_GET['id'];
 $rScript = $_GET['service'];
  
 $log = MAIN_DIR . 'logs/build/' . $rChannel . '.log';
 $old_log = MAIN_DIR . 'logs/build/' . $rChannel . '_' . $rTime . '.log';

 if ($rAction == 'START') {
  $rTime = time();
  addPersistence($rScript, $rChannel);
  if(file_exists($log))
  {
    exec('mv '. $log .' '. $old_log);
  }
  exec('/home/wvtohls/php/bin/php ' . MAIN_DIR . 'includes/' . $rScript . '.php START ' . $rChannel . ' > ' . $log . ' 2>&1 &');
  echo json_encode(['status' => true]);
  exit();
 }
 else if ($rAction == 'STOP') {
  removePersistence($rScript, $rChannel);
  unlink($log);
  exec('/home/wvtohls/php/bin/php ' . MAIN_DIR . 'includes/' . $rScript . '.php STOP ' . $rChannel . ' > /dev/null &');
  echo json_encode(['status' => true]);
  exit();
 }
}
else if ($rAction == 'GET_WHITELIST') {
 $rReturn = [];
 $rWhitelist = explode("\n", file_get_contents(MAIN_DIR . 'config/whitelist.conf'));

 foreach ($rWhitelist as $rIP) {
  $rSplit = explode(' ', preg_replace('/\\s+/', ' ', $rIP));

  if (strtolower($rSplit[0]) == 'allow') {
   $rIP = rtrim($rSplit[1], ';');

   if ($rIP != 'all') {
    $rReturn[] = $rIP;
   }
  }
 }

 echo json_encode(['status' => true, 'whitelist' => $rReturn]);
 exit();
}
else if ($rAction == 'SET_WHITELIST') {
 $rWhitelist = json_decode($_GET['whitelist']);
 if ($rWhitelist && (0 < count($rWhitelist))) {
  $rString = '';

  foreach ($rWhitelist as $rIP) {
   $rString .= 'allow ' . $rIP . ';' . "\n";
  }

  $rString .= 'deny all;';
  file_put_contents(MAIN_DIR . 'config/whitelist.conf', $rString);
  echo json_encode(['status' => true]);
  exit();
 }
}

echo json_encode(['status' => false]);
exit();

?>