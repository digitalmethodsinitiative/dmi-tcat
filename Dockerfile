#
FROM jrei/systemd-ubuntu:18.04

RUN apt-get update
RUN apt-get install -y lsb-release iproute2 sudo

#copy docker setup script
COPY docker/setup.sh tcat-install-linux.sh

#copy config
COPY docker/config config.txt

#create file for crontab
RUN touch /etc/crontab

#create log file to see whats happening with the crontab
RUN touch /var/log/cron.log

#run docker setup script
RUN chmod a+x tcat-install-linux.sh
RUN /bin/bash ./tcat-install-linux.sh -y -c config.txt


#expose port
EXPOSE 80


#start apache and mysql and twitter capture stream once (also started as cronjob, but it seem to have issues sometimes)
CMD sudo service mysql start && apachectl -D FOREGROUND && /usr/bin/php /var/www/dmi-tcat/capture/stream/dmitcat_track.php &
