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

#run docker setup script
RUN chmod a+x tcat-install-linux.sh
RUN /bin/bash ./tcat-install-linux.sh -y -c config.txt


#expose port
EXPOSE 80


#start apache and mysql
CMD sudo service mysql start && apachectl -D FOREGROUND
