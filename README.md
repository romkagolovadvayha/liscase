# Blog with generate contents

## Settings

### Install
- `composer install`
- `init`
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