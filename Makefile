.PHONY: default build-wd-backend

default:
	@echo "default"


t=$(shell date '+%Y%m%d%H%M')

repo1="registry.baidubce.com/wd-shop/wd-backend"
n1="${repo1}:${t}"

build-wd-backend:
	@echo "make build-01"
	sudo docker login --username=378830780a0d48a687a4fd584fb517cb registry.baidubce.com -p 17erDLco
	sudo docker build --no-cache=true -t ${n1} -f Dockerfile . --network=host
	sudo docker tag ${n1} ${repo1}:latest
	sudo docker push ${repo1}:latest

build-wd-backend-queue:
	@echo "make build-01"
	sudo docker login --username=378830780a0d48a687a4fd584fb517cb registry.baidubce.com -p 17erDLco
	sudo docker build --no-cache=true -t ${n1} -f queue.Dockerfile . --network=host
	sudo docker tag ${n1} ${repo1}:queue
	sudo docker push ${repo1}:queue


