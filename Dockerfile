FROM jrei/systemd-ubuntu:18.04

# Volume for mysql database data directory
VOLUME /var/lib/mysql

# Install necessary packages for setup.sh
RUN apt-get update && apt-get install -y lsb-release iproute2 sudo cron

# Copy application
COPY . /var/www/dmi-tcat/

# Create crontab file for setup.sh
RUN touch /etc/crontab

# Set working directory
WORKDIR /var/www/dmi-tcat

# Run docker setup script
RUN chmod a+x docker/setup.sh
RUN /bin/bash ./docker/setup.sh

# Expose port
EXPOSE 80

# Set default container environment variables
# These can be overwritten when running container
ENV SERVERNAME=localhost
ENV LETSENCRYPT=n

# Start apache, mysql, and cron
CMD ./docker/docker-entrypoint.sh
