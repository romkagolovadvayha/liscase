#!/bin/bash

rm -rf ./frontend/web/assets/*
echo "Frontend Cache Clear"

rm -rf ./backend/web/assets/*
echo "Backend Cache Clear"

./yii translate/clear-translate-cache 2>&1
echo "Translate Cache Clear"