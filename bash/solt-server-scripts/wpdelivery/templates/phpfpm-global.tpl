;;;;;;;;;;;;;;;;;;;;;
; FPM Configuration ;
;;;;;;;;;;;;;;;;;;;;;

pid = /var/run/php5-fpm.pid
error_log = /var/log/php5-fpm.log

emergency_restart_threshold = 3
emergency_restart_interval = 1m
process_control_timeout = 5s

events.mechanism = epoll

include=/etc/php5/fpm/pool.d/*.conf