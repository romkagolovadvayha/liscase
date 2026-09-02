#!/bin/bash

SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd -- "${SCRIPT_DIR}/.." && pwd)"
cd "${REPO_ROOT}"

rm -rf ./frontend/web/assets/*
echo "Frontend Cache Clear"

rm -rf ./backend/web/assets/*
echo "Backend Cache Clear"

./yii translate/clear-translate-cache 2>&1
echo "Translate Cache Clear"
