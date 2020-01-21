#
# PulseAI Dockerfile
# NodeJS Application - Generic
#

FROM debian:jessie-slim

RUN apt-get update -y && apt-get upgrade -y \
    && apt-get install -y build-essential curl wget apt-transport-https \
    && wget -O - https://notesalexp.org/debian/alexp_key.asc | apt-key add - \
    && apt-get update -y \
    && curl -sL https://deb.nodesource.com/setup_8.x | sh \
    && apt-get install -y nodejs

RUN mkdir -p /home/node/app
ADD . /home/node/app/

WORKDIR /home/node/app

EXPOSE 5000

CMD ["node", "index.js"]
