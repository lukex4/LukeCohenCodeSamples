#
# PulseAI Dockerfile
# NodeJS Application - Generic
#

FROM node:alpine

WORKDIR /home/node/app
COPY . .

RUN npm install && chmod a+x start-log && ./start-log &

RUN echo "30 * * * * /home/node/app/hourly" > /etc/crontabs/root \
    && chmod a+x /home/node/app/hourly

CMD crond -l 2 -f

EXPOSE 5000
