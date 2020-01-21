map \$http_host \$blogid {
    default       -999;
}

server {
  
        listen 80;
        listen [::]:80;

        server_name $WORDPRESS_URL *.$WORDPRESS_URL;

        root /webserver/domains/$WORDPRESS_URL/httpdocs;
        index index.php index.html;

        location / {
                #try_files \$uri \$uri/ /index.php?q=\$uri&\$args;
                try_files \$uri \$uri/ /index.php?\$args;

                #auth_basic 'OLT WordPress Production';
                #auth_basic_user_file /etc/nginx/.htpasswd;
        }


        # Cache asset files for five minutes
        location ~* .(woff|eot|ttf|svg|mp4|webm|jpg|jpeg|png|gif|ico|css|js)$ {
          expires 5m;
        }


        # Hand-off PHP requests to PHP-FPM
        location ~ \.php$ {
                try_files \$uri =404;
                fastcgi_split_path_info ^(.+\.php)(/.+)$;
                fastcgi_pass          unix:/var/run/php5-fpm.sock;
                fastcgi_index         index.php;
                fastcgi_read_timeout  120;
                fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
                include fastcgi_params;
        }


        # Serve wp-content files for each site
        location ~ ^/files/(.*)$ {
                try_files /wp-content/blogs.dir/\$blogid/\$uri /wp-includes/ms-files.php?file=\$1 ;
                access_log off;
                log_not_found off;
                expires max;
        }

}