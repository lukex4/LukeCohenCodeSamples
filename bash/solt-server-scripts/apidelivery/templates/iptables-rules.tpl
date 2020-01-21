*filter

-I INPUT -i lo -j ACCEPT

-I INPUT -m conntrack --ctstate RELATED,ESTABLISHED -j ACCEPT
-A INPUT -m conntrack --ctstate INVALID -j DROP
-A INPUT -p tcp --tcp-flags ALL NONE -j DROP
-A INPUT -p tcp ! --syn -m state --state NEW -j DROP
-A INPUT -p tcp --tcp-flags ALL ALL -j DROP

-A INPUT -p tcp --dport 22 -j ACCEPT
-A INPUT -p tcp --dport 5000 -j ACCEPT

-A INPUT -p tcp --dport 80 -j ACCEPT
-A INPUT -p tcp --dport 443 -j ACCEPT

-A INPUT -p tcp --dport 3306 --sport 3306 -j ACCEPT
-A OUTPUT -p tcp --dport 3306 --sport 3306 -j ACCEPT

-A INPUT -j DROP

-P INPUT DROP

COMMIT