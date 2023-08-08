FROM registry.cn-shanghai.aliyuncs.com/wandui/wd-backend:v1.0

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

ADD . /app

RUN apk update && \
    apk add --no-cache git 

RUN cd /app && composer install

EXPOSE 1024

ENV TZ Asia/Shanghai


CMD ["/usr/local/bin/php", "/app/think", "swoole"]

