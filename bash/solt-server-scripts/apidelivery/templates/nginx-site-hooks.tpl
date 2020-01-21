server {
        listen 80;
        listen [::]:80;

        server_name $SERVER_TLD;

        root /webserver/domains/$SERVER_TLD/httpdocs;
        index index.php index.html;

        #location / {
          #try_files \$uri \$uri/ =404;
        #}

        # Serve static files directly
        location ~* ^.+.(jpg|jpeg|gif|css|png|js|ico|html|xml|txt)$ {
          access_log        off;
          expires           max;
        }

        # Send PHP requests to PHP-FPM
        location ~ \.php$ {
          try_files \$uri =404;
          fastcgi_split_path_info ^(.+\.php)(/.+)$;
          fastcgi_pass unix:/var/run/php5-fpm.sock;
          fastcgi_index index.php;
          fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
          include fastcgi_params;
        }

}