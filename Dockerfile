#
FROM jrei/systemd-ubuntu:latest
RUN apt-get update
RUN apt-get install -y lsb-release iproute2 sudo
COPY docker/setup.sh tcat-install-linux.sh
COPY docker/config config.txt
RUN chmod a+x tcat-install-linux.sh
RUN touch /etc/crontab
RUN /bin/bash ./tcat-install-linux.sh -y -c config.txt

EXPOSE 80