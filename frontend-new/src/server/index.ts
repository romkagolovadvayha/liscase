import { getWSServer } from './websocket';

const WS_PORT = parseInt(process.env.WS_PORT || '4888', 10);

// Запускаем WebSocket сервер с обработкой ошибок
let wsServer;
try {
  wsServer = getWSServer(WS_PORT);
  
  // Обработка ошибок сервера (например, порт занят)
  if (wsServer && 'httpServer' in wsServer && wsServer.httpServer) {
    wsServer.httpServer.on('error', (error: any) => {
      if (error.code === 'EADDRINUSE') {
        console.warn(`[WebSocket] Port ${WS_PORT} is already in use. WebSocket server may already be running.`);
        console.warn(`[WebSocket] Continuing without WebSocket server. If you need to restart, stop the existing process first.`);
      } else {
        console.error('[WebSocket] Server error:', error);
      }
    });
  }
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

