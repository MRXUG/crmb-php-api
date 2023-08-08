FROM registry.cn-shanghai.aliyuncs.com/wandui/wd-backend:v1.0

ADD . /app

EXPOSE 1024

ENV TZ Asia/Shanghai


CMD ["/usr/local/bin/php", "/app/think", "swoole"]

