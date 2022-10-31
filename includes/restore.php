<?php

include "functions.php";
set_time_limit(0);
ini_set("default_socket_timeout", 15);
ini_set("memory_limit", -1);
$rTime = time();
foreach (getPersistence() as $rScript => $rChannels) {
    foreach ($rChannels as $rChannel) {
        exec(MAIN_DIR . "php/bin/php " . MAIN_DIR . "includes/" . $rScript . ".php START " . $rChannel . " > " . MAIN_DIR . "logs/build/" . $rChannel . "_" . $rTime . ".log 2>&1 &");
    }
}

?>