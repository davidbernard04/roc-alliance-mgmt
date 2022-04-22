# RoC Alliance Management
Alliance management helper for the [Rise of Cultures](https://www.innogames.com/games/rise-of-cultures/) mobile game.

# Installation
## Vagrant Installation (automated in a Virtual Machine)

Vagrant will automatically create a Virtual Machine on Ubuntu 20.04, install all the required dependencies, and configure the environment so it is ready to be used.

Pre-requistes:
- [VirtualBox](https://www.virtualbox.org) installed
- [Vagrant](https://www.vagrantup.com) installed

Open a terminal in the **roc-alliance-mgmt/vagrant** directory, and launch:
```
vagrant up
```

Might take some time since this downloads a complete Ubuntu virtual machine.

Once completed, you are ready to upload screenshots at this address:
- http://127.0.0.1:8080/

## Docker Installation

Pre-requistes:
- [Docker](https://docs.docker.com/get-docker/) installed

Build the docker image:
```
# From base folder (and not from docker sub-folder)
cd roc-alliance-mgmt/

# Trailing "." is important
docker build --tag="test/roc-alliance-mgmt:latest" -f docker/Dockerfile .
```

Create and start the container:
```
# Change 8080 to the local port you want
docker run -dit --rm --name "mytest" -p 8080:80/tcp test/roc-alliance-mgmt
```

Note that the above command create a volatile container, that is destroyed when stopped.

If need be, the following command give you shell access inside the container:
```
docker exec -it "mytest" /bin/bash
```

## Manual Installation with Composer

This procedure has been tested on the following environment:
- Ubuntu 20.04
- Apache with PHP 7.4

Install the OCR engine:
```
apt-get install tesseract-ocr
```

Install Apache and PHP:
```
apt-get install curl apache2 php php-sqlite3 php-gd php-xml php-curl php-cli php-mbstring
```

Install web app dependencies with composer:
```
cd roc-alliance-mgmt/composer

composer install
```
