adduser --system --shell /bin/false --group --disabled-login wvtohls
chown -R wvtohls:wvtohls /home/wvtohls
apt-get -y install libxslt1-dev nscd htop libonig-dev libzip-dev software-properties-common aria2
add-apt-repository ppa:xapienz/curl34 -y
apt-get update
apt-get install libcurl4 curl
wget -q -O /tmp/libpng12.deb http://mirrors.kernel.org/ubuntu/pool/main/libp/libpng/libpng12-0_1.2.54-1ubuntu1_amd64.deb
dpkg -i /tmp/libpng12.deb
apt-get install -y
rm /tmp/libpng12.deb
chmod +x /home/wvtohls/bin/ffmpeg
chmod +x /home/wvtohls/bin/ffprobe
chmod +x /home/wvtohls/bin/mp4decrypt
chmod +x /home/wvtohls/bin/mp4dump
chmod +x /home/wvtohls/php/bin/php
chmod +x /home/wvtohls/php/sbin/php-fpm
chmod +x /home/wvtohls/nginx/sbin/nginx
ufw allow 18000
ufw allow 18001