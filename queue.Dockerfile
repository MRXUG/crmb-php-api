FROM registry.cn-shanghai.aliyuncs.com/wandui/wd-backend:v1.0

ADD . /app


ENV TZ Asia/Shanghai


CMD ["/usr/local/bin/php", "/app/think", "queue:work","--tries=2"]

