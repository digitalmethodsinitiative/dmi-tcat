#
FROM jrei/systemd-ubuntu:18.04

# Combine apt-get update and install per
# https://docs.docker.com/develop/develop-images/dockerfile_best-practices/#run
RUN apt-get update && apt-get install -y lsb-release iproute2 sudo cron

#copy docker setup script
COPY docker/setup.sh tcat-install-linux.sh

#copy config
COPY docker/config config.txt

#create file for crontab
RUN touch /etc/crontab

#run docker setup script
RUN chmod a+x tcat-install-linux.sh
RUN /bin/bash ./tcat-install-linux.sh -y -c config.txt

#expose port
EXPOSE 80

# Set working directory
WORKDIR /var/www/dmi-tcat

#start apache and mysql
CMD sudo service cron start && service mysql start && apachectl -D FOREGROUND
