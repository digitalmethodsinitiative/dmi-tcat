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
This Docker image uses a modified version of the installer (helpers/tcat-install-linux.sh) as a shortcut for easy deployment with docker.


1. Add config in `./docker/config`
2. build image:
`docker image build -t tcat:1.0 .`
3. run image:
`docker container run --publish 80:80 --detach --name tcat tcat:1.0`


## Issues

Please use the issue templates when reporting issues and bugs. 

## Status

Nice way to describe the fact that we don't have much 

## Contributing

We are happy to receive suggestions and improvements.

## License

Apache License Version 2.0
