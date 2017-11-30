# Testing code

You will need installed: docker-compose, unzip and envsubst.

1. Copy env.template to .env and configure there your needed variables

1. Get a domain name that connects directly with your computer, we use ngrok for development but you can use localtunnel or simply create nat rules in your router.

1. Execute `./launch.sh -h` to check syntax, ex: `./launch.sh -v 1.7 -d hostname.ngrok.io`

1. Let docker-compose do its magic, after you get the message `Almost! Starting Apache now..`, it will be ready.

1. Visit http://hostname.ngrok.io


