.PHONY: default build-wd-backend

default:
	@echo "default"


t=$(shell date '+%Y%m%d%H%M')

repo1="registry.baidubce.com/wandui/wd-backend"
n1="${repo1}:${t}"

build-wd-backend:
	@echo "make build-01"
	sudo docker login --username=943d56bb0ed84012a3e2daeb1ece3be5 registry.baidubce.com -p EVMcPGnt3wTPQttVV9f3
	sudo docker build --no-cache=true -t ${n1} -f Dockerfile . --network=host
	sudo docker tag ${n1} ${repo1}:latest
	sudo docker push ${repo1}:latest


