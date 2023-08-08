.PHONY: default build-wd-backend

default:
	@echo "default"


t=$(shell date '+%Y%m%d%H%M')

repo1="registry.cn-shanghai.aliyuncs.com/wandui/server-http"
repo2="registry.cn-shanghai.aliyuncs.com/wandui/server-queue"
n1="${repo1}:${t}"
n2="${repo2}:${t}"

build-wd-backend:
	@echo "make backend"
	sudo docker login --username=w947576702 registry.cn-shanghai.aliyuncs.com -p BmmNS,Xrl
	sudo docker build --no-cache=true -t ${n1} -f Dockerfile . --network=host
	sudo docker tag ${n1} ${repo1}:latest
	sudo docker push ${repo1}:latest

build-wd-backend-queue:
	@echo "make queue"
	sudo docker login --username=w947576702 registry.cn-shanghai.aliyuncs.com -p BmmNS,Xrl
	sudo docker build --no-cache=true -t ${n2} -f queue.Dockerfile . --network=host
	sudo docker tag ${n2} ${repo2}:latest
	sudo docker push ${repo2}:latest


