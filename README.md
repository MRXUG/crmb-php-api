# 万对商城
### 1. 安装
#### 1. 环境安装
    - linux 系统环境
    - 需要 php 7.4 版本
    - 安装 swoole 4.8 扩展
    - redis 扩展
    - fileinfo 扩展
    - redis >= 5.0
    - mysql >= 8.0
#### 2. 项目运行
```shell
php think swoole
```
#### 3. 查看项目支持的路由
```shell
php think route:list
```
#### 4. 伪静态
```
# 系统后台
location /admin {
  alias /www/service/ncrmeb/public/system;
  try_files $uri $uri/ /index.html last;
  index index.html;
}

# 资源地址
location /resource {
  alias /www/resource;
}

# 系统后台
location /h5 {
  alias /www/service/ncrmeb/public/h5;
  try_files $uri $uri/ /index.html last;
  index index.html;
}


# 商户后台
location /merchant {
  alias /www/service/ncrmeb/public/mer;
  try_files $uri $uri/ /index.html last;
  index index.html;
}

```
