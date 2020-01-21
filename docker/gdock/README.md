# GDOCK was used in development and other experimental uses. Effectively it plugs into a Google Cloud (GC) account and on deployment of a Docker site locally, will provision a GC virtual server, and deploy the same site on that server, with a subdomain automatically assigned.

# THIS WAS EXTREMELY USEFUL FOR QUICK LAUNCH OF DEMOS OR BITS OF APIS, ETC.

# G-DOCK

You need the Dockerfile, docker-compose.yml, apache.conf, and g-dock.sh in the root.

You'll need to set up the Google Cloud and all that stuff. Just read the Dockerfile and g-dock.sh if you wish. I will re-write this to explain prerequisites later.

g-dock should be placed and run from inside the same directory as your web/app.

## Prerequisites

- Docker
- gcloud CLI
- Etc.

## Warning about existing images

As this workflow is designed purely for development purposes, gdock will kill and delete and existing Docker containers and GCP VMs, as well as the copy of the image in the GCP Container Registry. Running this completely wipes out what was there before and replaces it with a running version of your latest code.

## Manual execution

To run:

```
./gcloud-autodeploy.sh GCLOUD_PROJECTID PROJECT_NAME GCP_SERVICEACCOUNT FQDN GDNS_ZONENAME
```

e.g:

```
./gcloud-autodeploy.sh personal-201216 billing-core 29112554465-compute@developer.gserviceaccount.com dev.italkincode.com i-talk-in-code
```

This example should result in the Docker image being deployed to GCP, accessible via the FQDN (domain name): http://billing-core.dev.italkincode.com.

_I will write a proper README when I can be bothered_
