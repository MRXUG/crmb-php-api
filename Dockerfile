FROM registry.baidubce.com/wd-shop/wd-backend:1.1

ADD . /app

EXPOSE 1024

ENV TZ Asia/Shanghai


CMD ["/usr/local/bin/php", "/app/think", "swoole"]

