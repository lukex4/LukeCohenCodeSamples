; Start a new pool named 'www'.
[www]

listen = /var/run/php5-fpm.sock
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
slowlog = /var/log/www.log.slow

security.limit_extensions = .php