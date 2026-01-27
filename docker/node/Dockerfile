FROM node:18-alpine

WORKDIR /app

# Копируем package.json
COPY node/package.json ./

# Устанавливаем зависимости
RUN npm install --production

# Копируем все Node.js сервисы
COPY node/ ./

# Создаем пользователя для запуска
RUN addgroup -g 1001 -S nodejs && \
    adduser -S nodejs -u 1001 && \
    chown -R nodejs:nodejs /app

USER nodejs

# Открываем порты для различных сервисов
EXPOSE 3000 3001 3002 3003 3004

# Запуск через PM2 или supervisor
CMD ["node", "index.js"]


