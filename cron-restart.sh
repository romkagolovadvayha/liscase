#!/usr/bin/env bash
set -e

./yii crontask/restart

crontab -l | sed -E '/cd \/var\/www\/www-root\/data\/var\/www\/prostoj\.store /d' | crontab -