@echo off
REM Full Docker environment rebuild script (Windows)
REM This will destroy all data and rebuild from scratch

echo 🔥 Full rebuild: stopping containers...
docker-compose down -v

echo 🗑️ Removing MySQL volume...
docker volume rm liscase_mysql-data 2>nul

echo 🚀 Starting fresh environment...
docker-compose up -d

echo 📋 Following logs (Ctrl+C to exit)...
docker-compose logs -f


