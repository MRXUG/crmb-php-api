#!/usr/bin/env bash

set -e

now=$(date +%s)
#//获取当前路径（因为放在build文件夹下所以加上/../）
base=$( cd "$(dirname "$0" )" ; pwd -P )
echo "$base"
echo "开始远程部署"
SERVER=$1
if [ "$SERVER" == "backend" ]; then
  echo "环境为: backend"
  make build-wd-backend
elif [ "$SERVER" == "job" ]; then
  echo "环境为: job"
  make build-wd-backend
elif [ "$SERVER" == "queue" ]; then
  echo "环境为: queue"
  make build-wd-backend-queue
else
  echo "未指定环境或不支持的环境 $SERVER, 目前支持的环境有:backend,job,queue"
  exit 1
fi

echo "需要进行远程部署吗? (Y/n)"
read answer
if [ "$answer" != "" ] && [ "$answer" != "Y" ] && [ "$answer" != "y" ] ;then
  echo "镜像打包完成"
  exit 0
fi

if [ "$SERVER" == "backend" ]; then
  echo "start:server1"
  ssh -p 22 ubuntu@124.220.63.34 "cd /data/wd-backend && sudo ./run.sh"
  echo "start:server2"
  ssh -p 22 ubuntu@122.51.253.14 "cd /data/wd-backend && sudo ./run.sh"
elif [ "$SERVER" == "job" ]; then
  echo "start:common-job"
  ssh -p 22 ubuntu@124.220.108.83 "cd /data/wd-backend && sudo ./run.sh"
elif [ "$SERVER" == "queue" ]; then
  echo "start:common-queue"
  ssh -p 22 ubuntu@124.220.108.83 "cd /data/wd-backend && sudo ./run_queue.sh"
else
  echo "未指定环境或不支持的环境 $SERVER, 目前支持的环境有:backend,job,queue"
  exit 1
fi
