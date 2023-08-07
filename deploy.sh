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


#echo "打包dist"

#rm -rf $base/deploy/$ENV_TYPE
#mkdir -p $base/deploy/$ENV_TYPE

#tar -zcvf $base/deploy/$ENV_TYPE/$now.tar.gz -C $base/dist .
#echo "打包完成: $base/deploy/$ENV_TYPE/$now.tar.gz"

#echo "需要进行远程部署吗? (Y/n)"
#read answer
#if [ "$answer" != "" ] && [ "$answer" != "Y" ] && [ "$answer" != "y" ] ;then
#  echo "本地打包完成!"
#  exit 0
#fi

#echo "进行远程部署"
#server_host="ubuntu@124.220.108.83"
#server_port=22
#server_dist="/data/"
#project_name="system" #文件夹名
#if [ "$ENV_TYPE" = "test" ]; then
#  project_name="system-test"
#fi

# 指定当前目录 请保证处于此项目根目录
#echo  "[$ENV_TYPE] 清理资源"
#ssh -p $server_port $server_host "rm -rf $server_dist/$project_name/*"

#ssh -p $server_port $server_host "mkdir -p $server_dist/$project_name/"

#echo "[$ENV_TYPE] 上传资源"
#scp -P $server_port $base/deploy/$ENV_TYPE/$now.tar.gz $server_host:$server_dist/$project_name/

#echo "[$ENV_TYPE] 上传资源成功, 开始部署"
#ssh -p $server_port $server_host "tar -xzf $server_dist/$project_name/$now.tar.gz -C $server_dist/$project_name"

#rm -rf $base/deploy/$ENV_TYPE
#echo "部署成功!"
