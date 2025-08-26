#!/usr/bin/env bash
set -e
# 1) чистим наследие "cd ... && ./yii ..."
crontab -l | sed -E '/cd \/var\/www\/www-root\/data\/var\/www\/prostoj\.store && \.\/yii /d' | crontab -
# 2) стандартный рестарт из библиотеки
./yii crontask/restart