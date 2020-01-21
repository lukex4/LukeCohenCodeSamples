**Used to deploy multiple servers, with a built-in BASH script to automatically pull the latest MASTER branch of a remote git repo when it changes. Includes Redis and Node**

It builds a secure Debian-based LEMP server, and downloads website files from Github.
Debian server is fully configured, including SSH keys, firewall, DoS protection, memory swap, and optimal NGINX configuration. Configuration options are available in /templates, including things like firewall and NGINX options, etc.
