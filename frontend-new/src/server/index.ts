import { getWSServer } from './websocket';

const WS_PORT = parseInt(process.env.WS_PORT || '4888', 10);

// Запускаем WebSocket сервер с обработкой ошибок
let wsServer: ReturnType<typeof getWSServer> | undefined;
try {
  wsServer = getWSServer(WS_PORT);
  
  // Обработка ошибок сервера (например, порт занят)
  // Примечание: httpServer является приватным свойством, поэтому мы не можем напрямую обращаться к нему
  // Ошибки обрабатываются внутри класса WSServer
} catch (error: any) {
  if (error.code === 'EADDRINUSE') {
    console.warn(`[WebSocket] Port ${WS_PORT} is already in use. Skipping WebSocket server startup.`);
    console.warn(`[WebSocket] If you need to restart, stop the existing process first.`);
    // Создаем пустой объект, чтобы не ломать импорты
    wsServer = {
      close: () => {},
      broadcast: () => {},
    } as any;
  } else {
    console.error('[WebSocket] Failed to start server:', error);
    // Не выбрасываем ошибку, чтобы не падал весь процесс
    wsServer = {
      close: () => {},
      broadcast: () => {},
    } as any;
  }
}

// Graceful shutdown
process.on('SIGINT', () => {
  console.log('\nShutting down WebSocket server...');
  if (wsServer && typeof wsServer.close === 'function') {
    wsServer.close();
  }
  process.exit(0);
});

process.on('SIGTERM', () => {
  console.log('\nShutting down WebSocket server...');
  if (wsServer && typeof wsServer.close === 'function') {
    wsServer.close();
  }
  process.exit(0);
});

// Экспортируем для использования в API routes
export { wsServer };
export { getWSServer };

