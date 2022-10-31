rm /home/wvtohls/logs/error.log
cd /home/wvtohls/logs/build/ && find . -name "*_*.log" -print0 | xargs -0 rm
cd /home/wvtohls/logs/ffmpeg/ && find . -name "*_*.log" -print0 | xargs -0 rm
rm /home/wvtohls/tmp/*.txt