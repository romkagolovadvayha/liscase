# Blog with generate contents

## Settings

### php.ini
- `max_execution_time=600`
- `export COMPOSER_PROCESS_TIMEOUT=600`
- `composer config --global process-timeout 2000`

### Queues
- `queue-open-ai`
> debug `yii queue-open-ai/run --verbose=1`

### Implemented
- [ ] Categories
    - [x] Generate Categories
    - [x] Backend Categories
    - [ ] Frontend Categories
        - [x] Seo Url
- [ ] Posts
    - [x] Generate Posts
    - [x] Backend Posts
    - [ ] Frontend Posts
        - [x] Seo Url
- [ ] Images
    - [ ] Generate Images
    - [ ] Backend Images
    - [ ] Frontend Images
        - [x] Seo Url
- [ ] Comments
    - [ ] Generate Comments
    - [ ] Backend Comments
    - [ ] Frontend Comments
- [ ] Users
    - [ ] Generate Users
    - [x] Backend Users
    - [ ] Registration Users
- [ ] Design
    - [ ] Settings Design
- [ ] Admin Services
    - [ ] Ping Services
    - [ ] Task Manager