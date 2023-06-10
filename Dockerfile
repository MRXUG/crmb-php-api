FROM koiding/docker-php-7:latest

ADD . /app

ENV TZ Asia/Shanghai


CMD ["/usr/local/bin/php", "/app/think", "swoole"]

