FROM jrei/systemd-ubuntu:18.04

# Volume for mysql database data directory
VOLUME /var/lib/mysql

# Install necessary packages for setup.sh
RUN apt-get update && apt-get install -y lsb-release iproute2 sudo cron

# Copy docker setup.sh script
COPY docker/setup.sh tcat-install-linux.sh

# Copy config
COPY docker/config config.txt

# Create crontab file for setup.sh
RUN touch /etc/crontab

# Run docker setup script
RUN chmod a+x tcat-install-linux.sh
RUN /bin/bash ./tcat-install-linux.sh -y -c config.txt

# Expose port
EXPOSE 80

# Set working directory
WORKDIR /var/www/dmi-tcat

# Start apache, mysql, and cron 
CMD sudo service cron start && service mysql start && apachectl -D FOREGROUND
