server {

       listen 80;
       listen [::]:80;

       server_name default.com www.default.com;

       root /webserver/domains/default.com/httpdocs;
       index index.html;

       location / {
               try_files $uri $uri/ =404;
       }
}
