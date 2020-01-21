#!/bin/sh

#
# This is a single-use script to configure the web server accordingly.
#
# This can be run against newly-provisioned servers as well as existing servers on reboot.
#


#
#
# Create a file containing our custom firewall rules
#
mkdir -p /etc/iptables

cat <<EOF > /etc/iptables/rules.custom
*filter

-I INPUT -i lo -j ACCEPT

-I INPUT -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT
-A INPUT -m conntrack --ctstate INVALID -j DROP
-A INPUT -p tcp --tcp-flags ALL NONE -j DROP
-A INPUT -p tcp ! --syn -m state --state NEW -j DROP
-A INPUT -p tcp --tcp-flags ALL ALL -j DROP

-A INPUT -p tcp --dport 5000 -j ACCEPT

-A INPUT -p tcp --dport 80 -j ACCEPT
-A INPUT -p tcp --dport 443 -j ACCEPT

-A INPUT -j DROP

-P INPUT DROP

COMMIT
EOF


#
#
# Load custom firewall rules into iptables
#
iptables --flush
iptables-restore < /etc/iptables/rules.custom


#
#
# Create a memory swapfile if it doesn't already exist
#
FILE='/var/swap.img'
if [ -f $FILE ]; then
 echo "There's already a swapfile."
 swapon /var/swap.img
else
 echo "The File '$FILE' Does Not Exist"

 cd /var && touch swap.img && chmod 600 /var/swap.img
 dd if=/dev/zero of=/var/swap.img bs=1024k count=1000
 mkswap /var/swap.img
 swapon /var/swap.img

fi


#
#
# Install DDOS Deflate with custom install script
#
bash /ddos-install.sh


#
#
# Set our custom SSH daemon rules and restart the SSH service
#
rm -rf /etc/ssh/sshd_config

cat <<EOF > /etc/ssh/sshd_config
#
# Custom SSH daemon rules
#

Port 5000
Protocol 2

HostKey /etc/ssh/ssh_host_rsa_key
HostKey /etc/ssh/ssh_host_dsa_key
HostKey /etc/ssh/ssh_host_ecdsa_key
HostKey /etc/ssh/ssh_host_ed25519_key

UsePrivilegeSeparation yes

KeyRegenerationInterval 3600
ServerKeyBits 1024

SyslogFacility AUTH
LogLevel VERBOSE

LoginGraceTime 120
PermitRootLogin without-password
StrictModes yes
AllowUsers root

ClientAliveInterval 120
ClientAliveCountMax 720

RSAAuthentication yes
PubkeyAuthentication yes

IgnoreRhosts yes
RhostsRSAAuthentication no
HostbasedAuthentication no

PermitEmptyPasswords no

ChallengeResponseAuthentication no
PasswordAuthentication no
AllowTcpForwarding no
X11Forwarding no
X11DisplayOffset 10
PrintMotd no
PrintLastLog yes
TCPKeepAlive yes
AcceptEnv LANG LC_*
UsePAM yes
EOF

chmod 644 /etc/ssh/sshd_config

service ssh restart
service sshd restart


#
#
# Add gotham.local root public key to this instance
#
mkdir -p /root/.ssh/
cat <<EOF > /root/.ssh/authorized_keys
ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQDf49ipIoxLtPJsi+w0zugkox5cCxAEPhVU2wvqyk8t+c0BiyUyfOzpFalg8YFEDQhQJ6CV/1S5nIX99p9neCjNl16sWiLlkoWoWnc2AB/3VAQ1SrAj5CKuKnqw7LJtk/ZBBkLHnAJ/5JWKag7pRMQ+gvkLsp7YmYJw7GPsh+tzxkHMbBO/tOqX9mqgIplQHMAGV+PYcD07GukSyt6JepWMGR5UTYo5nnHHO1EQ0+gHJgP9GL34Bj8Aq7mEdFt4LiTNpklkibht64woVqoX5ugxWIIQmW27IX3GZaDe17yPnNscbh+2WVx lukework@gotham.remote
EOF


#
#
# Update APT package
#
apt-get update


FILE='/root/.web_installed'
if [ -f $FILE ]; then
 echo "Web has already been installed."
else
 echo "The File '$FILE' Does Not Exist"


 # Install nginx and PHP-FPM
 apt-get install -y nginx
 service nginx stop

 apt-get install -y php7.0-fpm php7.0-mongo php7.0-mysql php7.0-gd php7.0-curl php7.0-common php7.0-cli php7.0-dev php7.0-readline php-pear


 # Install git
 apt-get install -y git-core


 # Configure PHP-FPM
 service php7.0-fpm stop

 echo "extension = mongo.so" >> /etc/php/7.0/fpm/conf.d/20-mongo.ini

 echo "
fastcgi_param  SCRIPT_FILENAME    \$document_root\$fastcgi_script_name;
fastcgi_param  QUERY_STRING       \$query_string;
fastcgi_param  REQUEST_METHOD     \$request_method;
fastcgi_param  CONTENT_TYPE       \$content_type;
fastcgi_param  CONTENT_LENGTH     \$content_length;

fastcgi_param  SCRIPT_NAME        \$fastcgi_script_name;
fastcgi_param  REQUEST_URI        \$request_uri;
fastcgi_param  DOCUMENT_URI       \$document_uri;
fastcgi_param  DOCUMENT_ROOT      \$document_root;
fastcgi_param  SERVER_PROTOCOL    \$server_protocol;
fastcgi_param  REQUEST_SCHEME     \$scheme;
fastcgi_param  HTTPS              \$https if_not_empty;

fastcgi_param  GATEWAY_INTERFACE  CGI/1.1;
fastcgi_param  SERVER_SOFTWARE    nginx/\$nginx_version;

fastcgi_param  REMOTE_ADDR        \$remote_addr;
fastcgi_param  REMOTE_PORT        \$remote_port;
fastcgi_param  SERVER_ADDR        \$server_addr;
fastcgi_param  SERVER_PORT        \$server_port;
fastcgi_param  SERVER_NAME        \$server_name;

# PHP only, required if PHP was built with --enable-force-cgi-redirect
fastcgi_param  REDIRECT_STATUS    200;" > /etc/nginx/fastcgi.conf

# Set PHP-FPM global and pool settings

# Global
echo ";;;;;;;;;;;;;;;;;;;;;
; FPM Configuration ;
;;;;;;;;;;;;;;;;;;;;;

pid = /run/php/php7.0-fpm.pid
error_log = /var/log/php7.0-fpm.log

emergency_restart_threshold = 3
emergency_restart_interval = 1m
process_control_timeout = 5s

events.mechanism = epoll

include=/etc/php/7.0/fpm/pool.d/*.conf" > /etc/php/7.0/fpm/php-fpm.conf

# Pool
echo "; Start a new pool named 'www'.
; the variable \$pool can we used in any directive and will be replaced by the
; pool name ('www' here)
[www]

listen = /var/run/php7.0-fpm.sock
listen.allowed_clients = 127.0.0.1

user = www-data
group = www-data

listen.owner = www-data
listen.group = www-data

rlimit_files = 131072
listen.backlog = -1

pm = ondemand
pm.max_children = 50
pm.process_idle_timeout = 10s
pm.max_requests = 50

request_slowlog_timeout = 3s
slowlog = /var/log/\$pool.log.slow

security.limit_extensions = .php" > /etc/php/7.0/fpm/pool.d/www.conf



 # Create www-data /home directory, and import the www-data SSH key
 mkdir -p /home/www-data
 mkdir -p /home/www-data/.ssh

 # Import key for www-data
 echo "-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEAq32PlSRBuMYGM8jnpHY2f7Rti0RVZA7D9c87unxVBPKAkroh
geovEFuQA7LifyDYEDz1ZaPBD8/jH8nBODa6Iz+oIMQwYONMlgfEr99Vt8Aq2e26
zPVjzcoFnxzcikf+ag4AjBBIeomWkNs5F8GE2Urijy+0utqEHFs9kYKZvSndSaQC
I+1VxQQn+5xOIc6uIRDlXhU49nxI2SA4Q1nym/lmFFLpvaXkJ2Cl1gxAoarfcgPT
cDWq14oDvMXjuhlk8t0TCewp/gvoLcNM3y9UWaaZDLQqp9rfVyleU8eZUcPYY8Wf
UJ3w1DDzUrqy08D4lUiigqbfLvMTgvLawaCKIQIDAQABAoIBAHil5o0bq+0tzBFE
p8tpq1/e9S6EjbrONIlMGY5SiJHpdhFER+yZcDEG6ePgRz7/QWLzMAYo1duk+vpT
O8+rmPrRfxxGSm7vmuL2ZicBbdHPrZYSjVhCz8to9NmDOZlDmzbL0RC5J/SltbsF
sD4JVMh2ybvI6VKIB2fXvRIRGs4cbfS1Vj5ZtBfQ1ub6ddzlQqgd5ldA/GMmA/jy
2txMk7lM6Wsag76vvFOZ6TrJt3ZwyYUkaYGxme84q697yEroXO5f1pECgYEAy5na
ItNDhQONY3jQBEe2IPhu3FFlDSvfP7FE/qjUFpJjlvVNTi1vN0tIpUIZ1OQa3l93
KMNmVN8na+E09wL4dBEs2TnncLDjx3hfKuvGTgGQ9qTD3DfN/JtC+JGhqY9tEEIo
c1Ogceqy5Ro9FSy7mnFFM1eI+cgFbiEStLEs4pECgYBYrCG+7AbUn53C9k3qarho
FLN9+0OBHXC3ssjg+y7GLPViTu4qfowdvGMxFEoBPV8g176kHghdg81f5paWrXjk
lwBYCkLIJAloSkIbiXtCFLRa6gzMb4gdx/C54z8M0p3Bxf4w/VRTBHkFuHBjfxxd
QxeNTUJJ4E2QLbXm2aSR8QKBgQDGbaurRTsI5+1khNo3UayvdXCWSL2zmI58BMi5
aNFLLmDhPzGYf69ktHqYlVOXqLiHAYOgts+E5/gDq/lyw/JhLFwJWeW2bq+QOECI
FEGwxNYDi5FXjwuypZ8fos9r8dzZe6DwxRAZ9iNkJa5idLZiTwKihy14QvkH3DPe
5s5YAQKBgDWCek0jwkUTcRCRWSw/9tCqG+heeJCXvob9xrIGsQUiUtB0VBJhC35Y
jsn/ojGboBJ6zCc6WYxzTCCC6+hVNvp3s0qBwoVNcQyyfdpL/Se41JvjWC++YN2e
cPiz3KBOH7UUWP2fMhKODC6miASlUP0YKWofq0R3dBMSrc+rmCKs
-----END RSA PRIVATE KEY-----" > /home/www-data/.ssh/id_rsa

 echo "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQCrfY+VJEG4xgYzyOekdjZ/tG2LRFVkDsP1zzu6fFUE8oCSuiGB6i8QW5ADsuJ/5qDgCMEEh6iZaQ2zkXwYTZSuKPL7S62oQcWz2Rgpm9Kd1JpAIj7VXFBCf7nE4hzq4hEOVeFTj2fEjZIDhDWfKb+WYUUum9peQnYKXWDEChqt9yA9NwNarXigO8xeO6GWTy3RMJ7Cn+C+gtw0zfL1RZppkMtCqn2t9XKV5Tx5lRw9hjxZ9QnfDUMPNSurLTwPiVSKKCpt8u8xOC8trBoIoh www-data@blzd-host-1" > /home/www-data/.ssh/id_rsa.pub

 # Import key for root
 echo "-----BEGIN RSA PRIVATE KEY-----
MIIEogIBAAKCAQEAymjTxAKkWql895RblH3YCeg3mOnI+zxDJ9ciGYoLYT7g0W4J
Ef+6kCtFMXD1tkZVCOsVj2ET5mzuK4gjwJcrLrk2sjOq4Tv9zQDpp+9bd6tq6Gw1
3DYnFPfbOc3OrUM+8sMY7JSgIn+BuYLeLCP0lGZdpJAIzqTQXQASqEYqVOT+2gUw
eevtK6wcbZg9fwgYp2yY+1TglVTmJoJHBuzq+gpt9Y4sT9Ztscq7Wu5aLHan1/dA
C/hOvOFzuCSTAYCbofgeQ0zLuOiUl8+wpn2F9IDAdIQ4HrahK+CkckXCRh4+7XzP
nvNWVYfYVPzckoK/cR3WNIIRDSRCBVLUtZrvTQIDAQABAoIBAFv8uADkkn0BeeGq
ctRRPNWDy4Ca/tPu1zZL9xtUOUfAo7uKHmUnq0nJ4HBPvdtQq2SaMfovTDP0XWk7
UIdgaidZ3MgS238HyQRcyLCmQcnBky5ESd7bXpu2OKwWWEhdMmKvJoi+6SPD8DDq
z6y7KaECgYEA9QAvDvH6SRXZo4DePHORqV9bx6ad28JI+25TiOxqhitNr5aewqjb
72Lj/zZU+plxTEE1hCVnRyg2uPBz5zRBPHLGnp1qULOP8SKMbQrjhSH6vRsj4Qw7
CkTHLxZ2oHVjvEmTYngpeQr9X0DP2z12S9+slD2NvaKoUDRayTT3Np8CgYEA038j
aNNbpVt3CddIwebQ2q024um05WpcmSKcpWwah0iDlyyFnolPt76tZ9GnIyLhp+oT
qJSmTgWLdhwiHbuGSCJ4XDOz2wUzHGNiI+/lNyVqZ3MYqOHopStmFZh3TWkBxhjP
tVMGq+2aLoVvUp6lN2cSbLxA+p8crPGseH5qLpMCgYBgciUURg+YC8D0S3uw4nHZ
8g8IRj/oTdA7IlBCG4dHr+5SDAINcTm7P/uu8O19BUCDmzv8/Fhuu5bnMMj/oCN6
L1ifAiri//zPGSGcn2e3dgvlu7RhGFZ5kV+z2qzyN6P+cBxT7CevXbMmdzYcAVL7
RV8DsEOG/mwxvppqkK4s0wKBgAy+c3bEjZgoK8MXCtZMPzd+Cnmf1XuhbhT4JfI9
0ldmgi9gymhkI76RqcdAtc6DMo+4phiZZG/9G4sxZMjf3NaJ5TDBGMxQwuSqBGbf
9LLe+Utkfw7mFeul8s/IUSAD/MxgAFwPta40cf7toWicEJ6HAnA048F2RvcQ1PCp
xWIrAoGAfPDjEIyhVmM2hLk4ECV7uIIUtDV5a4oOLrth38If2vJTkhj2ZLDXQSye
KoaeDf0HCcOItQ4PryC0ZlLWjcaHs7eL6e/wUxAWbvlMEzhHMNOeOYB9LYJv0G0Z
l1qCocbRJUzAEIzZoqCE2UV9CLtjOhYiVGWZFvfwnoYTlnZudsQ=
-----END RSA PRIVATE KEY-----" > /root/.ssh/id_rsa

 echo "ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQCrfY+VJEG4xgYzyOekdjZ/tG2LRFVkDsP1zzu6fFUE8oCSuiGB6i8QW5ADsuJ/5qDgCMEEh6iZaQ2zkXwYTZSuKPL7S62oQcWz2Rgpm9Kd1JpAIj7VXFBCf7nE4hzq4hEOVeFTj2fEjZIDhDWfKb+WYUUum9peQnYKXWDEChqt9yA9NwNarXigO8xeO6GWTy3RMJ7Cn+C+gtw0zfL1RZppkMtCqn2t9XKV5Tx5lRw9hjxZ9QnfDUMPNSurLTwPiVSKKCpt8u8xOC8trBoIoh www-data@blzd-host-1" > /root/.ssh/id_rsa.pub

 # Set appropriate permissions on the imported keys
 chmod 600 /root/.ssh/id_rsa
 chmod 600 /root/.ssh/id_rsa.pub
 chmod 600 /home/www-data/.ssh/id_rsa
 chmod 600 /home/www-data/.ssh/id_rsa.pub

 # Set www-data ownership to its new home directory
 chown -R www-data:www-data /home/www-data

 # Tell Debian about www-data's new home directory
 usermod -d /home/www-data www-data

 # Create the 'config' file for www-data's and root's SSH sessions
 echo "Host bitbucket.org
    StrictHostKeyChecking no
	  IdentityFile /home/www-data/.ssh/id_rsa" > /home/www-data/.ssh/config

chown www-data:www-data /home/www-data/.ssh/config

 echo "Host bitbucket.org
      StrictHostKeyChecking no
   	  IdentityFile /root/.ssh/id_rsa" > /root/.ssh/config

 chown root:root /root/.ssh/config


 # Create /webserver directories for sites this server will host
 mkdir -p /webserver/domains/default.com


 # Chown everything in /webserver to www-data
 chown -R www-data:www-data /webserver


 # Clone site(s) sources into relevant directories
 cd /webserver/domains/default.com && sudo -u www-data git clone git@bitbucket.org:lukenicohen/default.com.git .


 # Chown everything in /webserver to www-data again, just to be sure
 chown -R www-data:www-data /webserver


 # Delete the default nginx site config file and sites-enabled directory (which we'll recreate shortly)
 rm -rf /etc/nginx/sites-available


 # Import the nginx config files
 cd /webserver && sudo -u www-data git clone git@bitbucket.org:lukenicohen/nginx-sites.git sites-available && chown root:root sites-available && sudo mv sites-available /etc/nginx


 # Create symlink to join sites-available and sites-enabled
 cd /etc/nginx/sites-enabled && cp -rs /etc/nginx/sites-available/* .


 # Create nginx microcache directory
 mkdir -p /usr/share/nginx/cache/fcgi


 # Import custom nginx.conf file
 echo "user www-data;
worker_processes 1;
pid /run/nginx.pid;

events {
	worker_connections 768;
	multi_accept on;
}

http {

	##
	# Basic Settings
	##

	sendfile on;
	tcp_nopush on;
	tcp_nodelay on;
	keepalive_timeout 65;
	types_hash_max_size 2048;
	# server_tokens off;

	# server_names_hash_bucket_size 64;
	# server_name_in_redirect off;

	include /etc/nginx/mime.types;
	default_type application/octet-stream;

	##
	# Microcache
	##
	fastcgi_cache_path /usr/share/nginx/cache/fcgi levels=1:2 keys_zone=microcache:10m max_size=2048m inactive=1h;


	##
	# SSL Settings
	##

	ssl_protocols TLSv1 TLSv1.1 TLSv1.2;
        ssl_prefer_server_ciphers on;
        ssl_dhparam /etc/ssl/certs/dhparam.pem;
        ssl_ciphers 'ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-AES256-GCM-SHA384:DHE-RSA-AES128-GCM-SHA256:DHE-DSS-AES128-GCM-SHA256:kEDH+AESGCM:ECDHE-RSA-AES128-SHA256:ECDHE-ECDSA-AES128-SHA256:ECDHE-RSA-AES128-SHA:ECDHE-ECDSA-AES128-SHA:ECDHE-RSA-AES256-SHA384:ECDHE-ECDSA-AES256-SHA384:ECDHE-RSA-AES256-SHA:ECDHE-ECDSA-AES256-SHA:DHE-RSA-AES128-SHA256:DHE-RSA-AES128-SHA:DHE-DSS-AES128-SHA256:DHE-RSA-AES256-SHA256:DHE-DSS-AES256-SHA:DHE-RSA-AES256-SHA:AES128-GCM-SHA256:AES256-GCM-SHA384:AES128-SHA256:AES256-SHA256:AES128-SHA:AES256-SHA:AES:CAMELLIA:DES-CBC3-SHA:!aNULL:!eNULL:!EXPORT:!DES:!RC4:!MD5:!PSK:!aECDH:!EDH-DSS-DES-CBC3-SHA:!EDH-RSA-DES-CBC3-SHA:!KRB5-DES-CBC3-SHA';
        ssl_session_timeout 1d;
        ssl_session_cache shared:SSL:50m;
        ssl_stapling on;
        ssl_stapling_verify on;
        add_header Strict-Transport-Security max-age=15768000;

	##
	# Logging Settings
	##

	access_log /var/log/nginx/access.log;
	error_log /var/log/nginx/error.log;

	##
	# Gzip Settings
	##

	gzip on;

	gzip_vary on;
	gzip_proxied any;
	gzip_comp_level 6;
	gzip_buffers 16 8k;
	gzip_http_version 1.1;
	gzip_types text/plain text/css application/json application/x-javascript text/xml application/xml application/xml+rss text/javascript;

	# gzip_vary on;
	# gzip_proxied any;
	# gzip_comp_level 6;
	# gzip_buffers 16 8k;
	# gzip_http_version 1.1;
	# gzip_types text/plain text/css application/json application/javascript text/xml application/xml application/xml+rss text/javascript;

	##
	# Virtual Host Configs
	##

	include /etc/nginx/conf.d/*.conf;
	include /etc/nginx/sites-enabled/*;
}" > /etc/nginx/nginx.conf


 # Start nginx and restart FPM
 service nginx start
 service php7.0-fpm start


 # Installation complete
 touch /root/.web_installed
 echo "web installed" >> /root/.web_installed


fi
