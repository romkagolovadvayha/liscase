# Blog with generate contents

## Settings

### Install
- `cd ~/.ssh && ssh-keygen`
- `apt install composer`
- `cd /var/www && git clone git@github.com:romkagolovadvayha/liscase.git`
- `sudo apt-get update`
- `sudo apt-get upgrade`
- `sudo apt install software-properties-common`
- `sudo add-apt-repository ppa:ondrej/php`
- `sudo apt update`
- `sudo apt-get install php7.4 php7.4-fpm`
- `sudo apt-get install php7.4-mysql php7.4-mbstring php7.4-xml php7.4-gd php7.4-curl`
- `curl -sS https://getcomposer.org/installer | php`
- `php composer.phar update`
- `php init`
- `nginx`
- `supervisor`

### php.ini
- `max_execution_time=600`
- `export COMPOSER_PROCESS_TIMEOUT=600`
- `composer config --global process-timeout 2000`

### Queues
- `queue-open-ai`
- `queue-midjourney`
> debug `yii queue-open-ai/run --verbose=1`

### Implemented
- [x] Categories
    - [x] Generate Categories
    - [x] Backend Categories
    - [x] Frontend Categories
        - [x] Seo Url
- [x] Posts
    - [x] Generate Posts
    - [x] Backend Posts
    - [x] Frontend Posts
        - [x] Seo Url
- [x] Images
    - [x] Generate Images
    - [x] Backend Images
    - [x] Frontend Images
        - [x] Seo Url
- [x] Comments
    - [x] Generate Comments
    - [x] Backend Comments
    - [x] Frontend Comments
- [x] Users
    - [x] Generate Users
    - [x] Backend Users
    - [x] Registration Users
- [x] Design
    - [x] Settings Design
- [x] Admin Services
    - [ ] Ping Services
    - [ ] Task Manager