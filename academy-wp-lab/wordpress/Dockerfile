FROM wordpress:6.9-php8.3-apache
# procps -> ps/top ; util-linux -> setsid/lscpu ; curl for convenience.
# These make the dropper + IR evidence behave like the real host.
RUN apt-get update \
 && apt-get install -y --no-install-recommends procps util-linux curl \
 && rm -rf /var/lib/apt/lists/*
