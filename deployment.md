# 测试站发布

- 版本：v1.0
- 日期：2026-10-19
- 用途：阶段 1 开发完成后，在现有宝塔服务器上发布，给企业微信做免登录验证

自动测试在开发机上跑，用假的企业微信。真人验收只在这台测试站上做。本地地址和企业微信对不上，不使用内网穿透。

本机没有 PHP、MySQL 时，用项目根目录的 Docker Compose 开发：`docker compose up --build` 后打开 `http://localhost:8000`。这只用于写代码和跑自动测试。企业微信免登录验收仍在宝塔测试站上做，不在 Docker 里做，也不用内网穿透。

---

## 1. 怎么发

用现在这台装了宝塔的服务器，单独建一个测试网站。测试站不另起一套 Docker，也不和别的网站共用数据库。

网站根目录指向 Laravel 的 `public`。员工从企业微信打开的地址，就是这个网站的 HTTPS 域名。

阶段 1 只发测试站。以后正式使用时换正式域名，再复制一份站点，不在测试站上改成正式。

---

## 2. 开发开始前要先备好

这几样不齐，代码写完也验不了免登录。

| 项 | 要求 |
|---|---|
| 域名 | 解析到这台服务器的公网 IP。企业微信不接受 IP，也不接受临时穿透域名 |
| 备案 | 服务器在中国大陆、企业微信是国内企业时，这个域名要已备案。没备案，可信域名和网页授权通常填不进去 |
| HTTPS | 宝塔里用 Let's Encrypt 签证书，强制 HTTPS |
| PHP | 8.3 或以上。在宝塔「软件商店」安装，网站选择这个版本 |
| 数据库 | 宝塔里的 MySQL 8.0 即可，单独建一个库和一个用户。不必为了验证去升到 8.4 |
| Redis | 宝塔软件商店安装。只给本机用，不要开放公网端口 |
| 出网 | 服务器能访问 `qyapi.weixin.qq.com` |
| 企业微信 | 已有自建应用。记下企业 ID、AgentId、Secret。应用可见范围包含要参与验证的门店和专柜 |

MySQL 的 3306 和宝塔面板端口不要对公网开放。

---

## 3. 宝塔里怎么建站

1. 网站 → 添加站点。填写测试域名。PHP 选 8.3。数据库选 MySQL，记下库名、用户名、密码。
2. 网站设置 → 网站目录：运行目录改为 `/public`。
3. 伪静态选择 `laravel`。
4. SSL → Let's Encrypt → 申请，并开启强制 HTTPS。
5. 该 PHP 版本的「禁用函数」里去掉 `putenv`、`proc_open`、`pcntl_signal`、`pcntl_alarm`。队列和读取配置需要它们。
6. 软件商店安装 Redis、进程守护管理器（Supervisor）。

代码放在站点目录，例如 `/www/wwwroot/crm-test.example.com`。用 Git 拉取，不要用压缩包来回上传。`.env` 只放在服务器上，不进仓库。

进程守护一条命令，目录选站点目录：

```bash
php artisan queue:work redis --sleep=1 --tries=3 --max-time=3600
```

同步按钮把任务放进这个队列。进程没在跑，页面会提示已提交，但部门和员工不会出现。

---

## 4. 企业微信里要填的地址

都用测试站的 HTTPS 域名，路径按开发时实际做的回调为准。下面是要接通的三项，缺一项免登录就不成立。

| 企业微信配置 | 填什么 |
|---|---|
| 可信域名 | 测试域名。按提示把校验文件放到网站 `public` 目录，浏览器能打开 `https://测试域名/WW_verify_xxx.txt` |
| 网页授权回调域名 | 同一个测试域名 |
| 企业可信 IP | 这台服务器的公网 IP。不填的话，同步通讯录会被拒绝 |

应用的可见范围要包含麦凯乐、罗斯福店、时代广场下面的专柜，否则那些店员在工作台看不到应用，也同步不到。

---

## 5. 第一次发布

在站点目录执行：

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

编辑 `.env`：

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://测试域名`
- 数据库名、用户、密码用宝塔刚建的
- `REDIS_HOST=127.0.0.1`
- 企业 ID、AgentId、Secret
- 队列连接用 `redis`

然后：

```bash
php artisan migrate --force
php artisan storage:link
php artisan filament:assets
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

创建唯一的管理员（没有企业微信人员编号，供密码入口使用）。具体命令在项目初始化时定成一个 artisan 命令，只在第一次发布执行。

在进程守护里启动队列，并确认状态是运行中。

浏览器打开 `https://测试域名`。管理员走密码入口，能看到部门列表、员工列表和「同步企业微信」。

---

## 6. 以后每次更新

代码合并进测试分支之后，在服务器站点目录执行：

```bash
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan filament:assets
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

`queue:restart` 让正在跑的队列在下一轮任务时加载新代码。不重启的话，同步仍会跑旧逻辑。

这条命令在项目里落成 `deploy/bt-update.sh`，开发阶段 1 时一并提交。服务器上执行这一个脚本即完成更新。

更新前先在开发机跑完自动测试。测试没过不发布。

---

## 7. 发布后怎么验证阶段 1

按 `development-plan.md` 第 4.8 节，全部在这个测试域名上做：

1. 管理员密码登录，点同步。部门里有麦凯乐、罗斯福店、时代广场，没有「其他（待设置部门）」。欧米茄是麦凯乐下面的专柜。
2. 欧米茄下的店员，门店显示麦凯乐，专柜显示欧米茄。
3. 再点一次同步，人数不增加。
4. 用一个已经同步、状态为在职的店员，从企业微信工作台打开应用。中间没有登录页，进入后看到自己的姓名。
5. 已禁用或已离职的人从工作台打开，进不去。

手机号是空的不挡住这次验证。免登录认的是人员编号。

---

## 8. 不这样做

- 不用 IP、不用内网穿透给企业微信当回调地址。
- 不把测试站和正式数据放进同一个数据库。
- 不在宝塔里开着 `APP_DEBUG=true` 给店员用。出错看 `storage/logs`。
- 不同步把 `.env`、企业微信 Secret 放进 Git。
