sudo sed -i '/home\/wvtohls/d' /etc/fstab
sleep 2
sudo echo $'\ntmpfs /home/wvtohls/hls tmpfs defaults,noatime,nosuid,nodev,noexec,mode=1777,size=90% 0 0' >> /etc/fstab
sudo echo $'\ntmpfs /home/wvtohls/video tmpfs defaults,noatime,nosuid,nodev,noexec,mode=1777,size=90% 0 0' >> /etc/fstab
sudo mount -av