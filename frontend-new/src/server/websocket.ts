import { WebSocketServer, WebSocket } from 'ws';
import { createServer, IncomingMessage, ServerResponse } from 'http';
import { parse } from 'url';
import { query } from '../lib/db';

interface Client {
  ws: WebSocket;
  userId?: number;
  steamId?: string;
  lastPong: number;
  isAlive: boolean;
}

export class WSServer {
  private wss!: WebSocketServer;
  private httpServer: any;
  private clients: Set<Client> = new Set();
  private clientsByUserId: Map<number, Set<Client>> = new Map();
  private heartbeatInterval: NodeJS.Timeout | null = null;

  constructor(port: number = 4888) {
    try {
      // Создаем HTTP сервер для WebSocket и API endpoints
      this.httpServer = createServer((req: IncomingMessage, res: ServerResponse) => {
        const parsedUrl = parse(req.url || '', true);
        
        console.log(`[WebSocket HTTP] ${req.method} ${parsedUrl.pathname}`);
        
        // API endpoint для отправки обновлений баланса - обрабатываем ДО WebSocket upgrade
        if (parsedUrl.pathname === '/api/balance-update' && req.method === 'POST') {
          console.log('[WebSocket HTTP] Processing balance update request');
          let body = '';
          req.on('data', (chunk) => {
            body += chunk.toString();
          });
          req.on('end', () => {
            try {
              const data = JSON.parse(body);
              console.log('[WebSocket] Received balance update request:', data);
              this.handleUpdatedBalance(data);
              res.writeHead(200, { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' });
              res.end(JSON.stringify({ success: true }));
            } catch (error: any) {
              console.error('[WebSocket] Error processing balance update:', error);
              res.writeHead(400, { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' });
              res.end(JSON.stringify({ success: false, error: error.message || 'Invalid JSON' }));
            }
          });
          return;
        }
        
        // Health check
        if (parsedUrl.pathname === '/health') {
          res.writeHead(200, { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' });
          res.end(JSON.stringify({ status: 'ok', clients: this.clients.size }));
          return;
        }
        
        // Для всех остальных запросов возвращаем 404
        // WebSocket upgrade будет обработан через событие 'upgrade'
        console.log(`[WebSocket HTTP] 404 for ${req.method} ${parsedUrl.pathname}`);
        res.writeHead(404);
        res.end();
      });

      // Создаем WebSocket сервер БЕЗ привязки к HTTP серверу
      // Мы обрабатываем upgrade вручную через событие 'upgrade'
      this.wss = new WebSocketServer({ noServer: true });

      // Обрабатываем WebSocket upgrade запросы явно
      this.httpServer.on('upgrade', (request: IncomingMessage, socket: any, head: Buffer) => {
        const parsedUrl = parse(request.url || '', true);
        
        console.log(`[WebSocket Upgrade] ${request.method} ${parsedUrl.pathname}`);
        console.log(`[WebSocket Upgrade] Headers:`, request.headers.upgrade, request.headers.connection);
        
        // Пропускаем только WebSocket подключения (не API endpoints)
        // WebSocket запросы обычно не имеют pathname или имеют другой формат
        if (parsedUrl.pathname && parsedUrl.pathname.startsWith('/api/')) {
          // Это не WebSocket запрос, закрываем соединение
          console.log(`[WebSocket Upgrade] Rejecting non-WebSocket request to ${parsedUrl.pathname}`);
          socket.destroy();
          return;
        }
        
        // Передаем запрос в WebSocket сервер для обработки
        console.log(`[WebSocket Upgrade] Handling WebSocket upgrade for ${parsedUrl.pathname}`);
        this.wss.handleUpgrade(request, socket, head, (ws) => {
          this.wss.emit('connection', ws, request);
        });
      });
      
      this.setupHandlers();
      this.startHeartbeat();
      
      this.httpServer.listen(port, () => {
        console.log(`[WebSocket] Server started on port ${port}`);
        console.log(`[WebSocket] HTTP API available at http://localhost:${port}/api/balance-update`);
      });

      // Обработка ошибки при прослушивании порта
      this.httpServer.on('error', (error: any) => {
        if (error.code === 'EADDRINUSE') {
          console.warn(`[WebSocket] Port ${port} is already in use. WebSocket server may already be running.`);
          console.warn(`[WebSocket] Skipping WebSocket server startup. If you need to restart, stop the existing process first.`);
          // Не выбрасываем ошибку, просто предупреждаем
        } else {
          console.error(`[WebSocket] Server error:`, error);
          throw error;
        }
      });
    } catch (error: any) {
      if (error.code === 'EADDRINUSE') {
        console.warn(`[WebSocket] Port ${port} is already in use. WebSocket server may already be running.`);
        // Не выбрасываем ошибку, чтобы не падал весь процесс
        return;
      }
      throw error;
    }
  }

  private setupHandlers() {
    this.wss.on('connection', (ws: WebSocket) => {
      const client: Client = {
        ws,
        lastPong: Date.now(),
        isAlive: true,
      };

      this.clients.add(client);
      console.log(`[WebSocket] Client connected. Total clients: ${this.clients.size}, Authenticated users: ${this.clientsByUserId.size}`);

      // Обработка pong для heartbeat
      ws.on('pong', () => {
        client.lastPong = Date.now();
        client.isAlive = true;
      });

      // Обработка сообщений от клиента
      ws.on('message', async (data: Buffer) => {
        try {
          const message = JSON.parse(data.toString());
          await this.handleMessage(client, message);
        } catch (error) {
          console.error('[WebSocket] Error parsing message:', error);
        }
      });

      // Обработка закрытия соединения
      ws.on('close', () => {
        this.handleDisconnect(client);
      });

      // Обработка ошибок
      ws.on('error', (error) => {
        console.error('[WebSocket] Client error:', error);
        this.handleDisconnect(client);
      });
    });
  }

  private async handleMessage(client: Client, message: any) {
    try {
      switch (message.action) {
        case 'auth':
          await this.handleAuth(client, message);
          break;
        case 'Pong':
          // Обработка app-level pong
          client.lastPong = Date.now();
          client.isAlive = true;
          break;
        case 'updatedBalance':
          // Обработка команды обновления баланса (от других сервисов)
          await this.handleUpdatedBalance(message);
          break;
        default:
          console.log('[WebSocket] Unknown action:', message.action);
      }
    } catch (error) {
      console.error('[WebSocket] Error handling message:', error);
    }
  }

  private async handleAuth(client: Client, message: any) {
    try {
      const { token, steam_id } = message;

      console.log('[WebSocket] Auth attempt:', { hasToken: !!token, hasSteamId: !!steam_id, tokenPreview: token ? token.substring(0, 10) + '...' : 'none', steamId: steam_id });

      if (!token || !steam_id) {
        console.log('[WebSocket] Auth failed: Missing token or steam_id');
        client.ws.send(JSON.stringify({ type: 'error', message: 'Missing token or steam_id' }));
        return;
      }

      // Получаем пользователя по токену (как в /api/auth/ws-token)
      console.log('[WebSocket] Querying user from database...');
      const users = await query<any>(`
        SELECT id, steam_id
        FROM user
        WHERE auth_key = ? AND status = 1
        LIMIT 1
      `, [token]);

      console.log('[WebSocket] User query result:', { found: users.length > 0, userId: users[0]?.id, dbSteamId: users[0]?.steam_id, receivedSteamId: steam_id });

      if (!users || users.length === 0) {
        console.log('[WebSocket] Auth failed: User not found with this token');
        client.ws.send(JSON.stringify({ type: 'error', message: 'Invalid token' }));
        return;
      }

      const user = users[0];
      
      // Проверяем, что steam_id совпадает
      if (user.steam_id !== steam_id) {
        console.log('[WebSocket] Auth failed: steam_id mismatch', { dbSteamId: user.steam_id, receivedSteamId: steam_id });
        client.ws.send(JSON.stringify({ type: 'error', message: 'Invalid steam_id' }));
        return;
      }

      client.userId = user.id;
      client.steamId = user.steam_id;

      // Добавляем в индекс по user_id
      if (!this.clientsByUserId.has(user.id)) {
        this.clientsByUserId.set(user.id, new Set());
      }
      this.clientsByUserId.get(user.id)!.add(client);

      console.log(`[WebSocket] Client authenticated. userId: ${user.id}, steamId: ${user.steam_id}`);
      console.log(`[WebSocket] Total clients for user ${user.id}: ${this.clientsByUserId.get(user.id)!.size}`);
      console.log(`[WebSocket] Total authenticated users: ${this.clientsByUserId.size}, Total clients: ${this.clients.size}`);
      client.ws.send(JSON.stringify({ type: 'auth', success: true }));
    } catch (error: any) {
      console.error('[WebSocket] Auth error:', error);
      console.error('[WebSocket] Auth error stack:', error.stack);
      client.ws.send(JSON.stringify({ type: 'error', message: 'Auth failed: ' + (error.message || 'Unknown error') }));
    }
  }

  private handleUpdatedBalance(message: any) {
    try {
      const { user_id, balance, balanceStr } = message;

      console.log('[WebSocket] handleUpdatedBalance called:', { user_id, balance, balanceStr });
      console.log('[WebSocket] Current state:', {
        totalClients: this.clients.size,
        authenticatedUsers: this.clientsByUserId.size,
        availableUserIds: Array.from(this.clientsByUserId.keys()),
      });

      if (!user_id) {
        console.warn('[WebSocket] No user_id provided in balance update');
        return;
      }

      const hash = Math.random().toString(36).substring(7);
      const response = {
        type: 'update.balance',
        code: 200,
        balanceStr: balanceStr || balance?.toString(),
        balance: balance,
        hash: hash,
      };

      console.log('[WebSocket] Prepared balance update message:', response);

      // Отправляем всем клиентам пользователя
      const userClients = this.clientsByUserId.get(user_id);
      console.log(`[WebSocket] Found ${userClients ? userClients.size : 0} clients for user_id ${user_id}`);
      
      if (userClients && userClients.size > 0) {
        let sentCount = 0;
        userClients.forEach((client) => {
          try {
            if (client.ws.readyState === WebSocket.OPEN) {
              client.ws.send(JSON.stringify(response));
              sentCount++;
              console.log(`[WebSocket] Sent balance update to client for user ${user_id}`);
            } else {
              console.warn(`[WebSocket] Client WebSocket not open, state: ${client.ws.readyState}`);
            }
          } catch (error) {
            console.error('[WebSocket] Error sending balance update:', error);
          }
        });
        console.log(`[WebSocket] Sent balance update to ${sentCount} clients`);
      } else {
        console.warn(`[WebSocket] No clients found for user_id ${user_id}. Total clients: ${this.clients.size}`);
        console.log('[WebSocket] Available user_ids:', Array.from(this.clientsByUserId.keys()));
      }
    } catch (error) {
      console.error('[WebSocket] Error handling balance update:', error);
    }
  }

  private handleDisconnect(client: Client) {
    this.clients.delete(client);

    if (client.userId) {
      const userClients = this.clientsByUserId.get(client.userId);
      if (userClients) {
        userClients.delete(client);
        if (userClients.size === 0) {
          this.clientsByUserId.delete(client.userId);
        }
      }
    }

    console.log(`[WebSocket] Client disconnected. Total clients: ${this.clients.size}`);
  }

  private startHeartbeat() {
    this.heartbeatInterval = setInterval(() => {
      const now = Date.now();
      const timeout = 45000; // 45 seconds

      this.clients.forEach((client) => {
        if (now - client.lastPong > timeout) {
          client.isAlive = false;
          client.ws.terminate();
          this.handleDisconnect(client);
          return;
        }

        // Отправляем ping
        try {
          if (client.ws.readyState === WebSocket.OPEN) {
            client.ws.ping();
            // Также отправляем app-level ping
            client.ws.send(JSON.stringify({ type: 'ping', ts: now }));
          }
        } catch (error) {
          console.error('[WebSocket] Error sending ping:', error);
          client.ws.terminate();
          this.handleDisconnect(client);
        }
      });
    }, 15000); // Каждые 15 секунд
  }

  // Публичный метод для отправки обновления баланса
  public async sendBalanceUpdate(userId: number, balance: number, balanceStr?: string) {
    await this.handleUpdatedBalance({
      user_id: userId,
      balance: balance,
      balanceStr: balanceStr || balance.toString(),
    });
  }

  public close() {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
    }
    this.wss.close();
    if (this.httpServer) {
      this.httpServer.close();
    }
    console.log('[WebSocket] Server closed');
  }
}

// Singleton instance
let wsServerInstance: WSServer | null = null;

export function getWSServer(port?: number): WSServer {
  if (!wsServerInstance) {
    wsServerInstance = new WSServer(port);
  }
  return wsServerInstance;
}

