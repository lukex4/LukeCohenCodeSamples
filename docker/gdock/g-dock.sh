#/bin/sh

#
# Command-line utility which builds a Docker image of the local container (in the working directory), deploys it to Google Compute, attaches it to the Google DNS zonefile, and returns a working web-based subdomain to access whatever the container would serve.
#

#
# THIS REQUIRES INSTALLATION OF THE gcloud sdk, AUTHORISATION WITH THE SDK ON THE MACHINE YOU'RE RUNNING ON, AND OBVIOUSLY A GOOGLE CLOUD ACCOUNT WITH THE RELEVANT SERVICES ENABLED (Container Registry, Virtual Compute, Cloud DNS)
#

#
# To run:
#
# Set the environment variables in .env-gdock, then when ready to compile, push to Google Compute and launch your Docker image, run ./g-dock.sh in the directory of your app/website.
#
# The script will, if successful, return the URL of the new instance.
#

# Load environment variables
source .env-gdock

## LOCAL

# Docker build the image
docker build -t gcr.io/$GCLOUD_PROJECTID/$PROJECT_NAME:0.0.1 .


## REMOTE

# Delete existing container image first
gcloud beta container images delete gcr.io/$GCLOUD_PROJECTID/$PROJECT_NAME --force-delete-tags

# Create the new container with this Docker image
gcloud docker -- push gcr.io/$GCLOUD_PROJECTID/$PROJECT_NAME:0.0.1

# Delete an existing VM if one exists for this PROJECT_NAME
gcloud beta compute instances delete $PROJECT_NAME

# Launch a new VM based on the newly pushed container
gcloud beta compute instances create-with-container $PROJECT_NAME --container-image gcr.io/$GCLOUD_PROJECTID/$PROJECT_NAME:0.0.1 --machine-type f1-micro --tags http-server --service-account=$GCP_SERVICEACCOUNT --format=json

# Make the IP of this new VM available in the script
export NEWVM_IP=$(gcloud beta compute instances describe $PROJECT_NAME --format=json | grep natIP | tr -d '""' | tr -d 'natIP' | tr -d ': ' | tr -d ',' | tr -d ' ')

# Add an A-record to the dev domain zonefile
NEW_ARECORD="$PROJECT_NAME.$FQDN."

## TODO: Remove any previous A records for this new sub-domain
gcloud dns record-sets transaction start -z=$GDNS_ZONENAME
gcloud dns record-sets transaction remove --name=$NEW_ARECORD --type=A -z=$GDNS_ZONENAME --ttl=0
gcloud dns record-sets transaction execute -z=$GDNS_ZONENAME

gcloud dns record-sets transaction start -z=$GDNS_ZONENAME
gcloud dns record-sets transaction add -z=$GDNS_ZONENAME --name=$NEW_ARECORD --type=A --ttl=0 $NEWVM_IP
gcloud dns record-sets transaction execute -z=$GDNS_ZONENAME

echo $NEW_ARECORD
