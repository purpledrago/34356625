#! /bin/bash
sudo killall -9 -u wvtohls nginx
sudo killall -9 -u wvtohls php-fpm
sudo killall -9 -u wvtohls ffmpeg
sudo killall -9 -u wvtohls php
sudo rm -rf /home/wvtohls/video/*
sudo rm -rf /home/wvtohls/hls/*
sudo rm -f /home/wvtohls/cache/*.db
sudo rm -f /home/wvtohls/php/daemon.sock