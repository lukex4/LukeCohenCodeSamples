#
# PulseAI Dockerfile
# Percona (MySQL 8) Service
#

FROM debian:jessie-slim

RUN apt-get update -y && apt-get upgrade -y \
    && apt-get install -y wget \
    && wget https://repo.percona.com/apt/percona-release_latest.generic_all.deb \
    && apt --fix-broken install -y && apt install -y ./percona-release_latest.generic_all.deb \
    && apt --fix-broken install -y && dpkg -i percona-release_latest.generic_all.deb
