FROM registry.baidubce.com/wd-shop/wd-backend:1.1

ADD . /app


ENV TZ Asia/Shanghai


CMD ["/usr/local/bin/php", "/app/think", "queue:work","--tries=2"]

