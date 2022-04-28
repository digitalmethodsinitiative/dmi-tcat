# DMI TCAT
![Docker Image CI](https://github.com/digitalmethodsinitiative/dmi-tcat/workflows/Docker%20Image%20CI/badge.svg)

The Digital Methods Initiative Twitter Capture and Analysis Toolset (DMI-TCAT) allows one to retrieve and collect tweets from Twitter and to analyze them in various ways.

## Installation

You can find detailed installation instructions in the [wiki](https://github.com/digitalmethodsinitiative/dmi-tcat/wiki/Installation-Guide)

### Requirements
- Twitter API credentials (these can be obtained from https://apps.twitter.com)
- One of the following Linux distributions:
  - Ubuntu 18.04
  - Debian 9.*
- ... or Docker (experimental)

### Debian and Ubuntu

Run:
````
curl https://raw.githubusercontent.com/digitalmethodsinitiative/dmi-tcat/master/helpers/tcat-install-linux.sh | sudo bash
````

### Docker
Our latest Docker images are availble on [Docker Hub](https://hub.docker.com/r/digitalmethodsinitiative/tcat).
1. Install [Docker Desktop](https://www.docker.com/products/docker-desktop), and start it. Note that on Windows, you may need to ensure that WSL (Windows Subsystem for Linux) integration is enabled in Docker. You can find this in the Docker setting in Settings -> Resources-> WSL Integration -> Enable integration with required distros.
## Basic installation
2. Run the command `docker run --publish 80:80 --volume tcat_data:/var/lib/mysql/ --detach --name tcat digitalmethodsinitiative/tcat:1.0` and Docker will download version 1.0 (or whatever tag with which you replace the "1.0")
- `--publish HOST_PORT:80` allows you to define which port on the host network is used. If you are using a different port, you may also need to add `-e SERVERNAME=localhost:HOST_PORT` where HOST_PORT is the desired port as this is used for internal links in the TCAT interface.
- `--volume volume_name:/var/lib/mysql/` ensures you are easily able to reuse your TCAT mysql database and recover data after you are no longer using TCAT
3. Open the logs to retrieve you login information via either Docker's interface or the command line `docker logs tcat` (installation may take some time, so you can either wait or run `docker logs -f tcat` to follow along)
4. Open http://localhost:80 in your browser and complete the configuration by providing your [Twitter API information](https://developer.twitter.com/en/portal/) and which type of tweet capturing you would like to do.
5. Congratulations! You can use the `admin` menu to create your first tweet capture bins
6. In the future, you can stop and start your TCAT container with:
`docker stop tcat`
and
`docker start tcat`
## Customize for you own server
The Docker installation also allows you to easily host TCAT on a server. In addition to the `SERVERNAME` environment variable, you can also use Let's Encrypt by adding `-e LETSENCRYPT=y` and `-e LETSENCRYPT_EMAIL=youremail@wherever.net`. You should also open port 443 for Let's Encrypt to work. Your full command might look like this:
`docker run --publish 80:80 --publish 443:443 --volume tcat_data:/var/lib/mysql/ --detach --name tcat -e SERVERNAME=my.website.com -e LETSENCRYPT=y -e LETSENCRYPT_EMAIL=myemail@my.website.com digitalmethodsinitiative/tcat:1.0`
## Further TCAT customization
Finally, if you wish to develop TCAT yourself, you can clone this repository and create your own image.
1. Clone this repository
`git clone https://github.com/digitalmethodsinitiative/dmi-tcat.git`
2. Build the image (from the directory where you have cloned TCAT):
`docker image build --progress=plain -t tcat:1.0 .`
3. Replace `digitalmethodsinitiative/tcat:1.0` with `tcat:1.0` from above in your `docker run` command

## Issues

Please use the issue templates when reporting issues and bugs.

## Status

Nice way to describe the fact that we don't have much

## Contributing

We are happy to receive suggestions and improvements.

## License

Apache License Version 2.0
