// API для отправки сообщений в WebSocket сервер
// Используется для обмена сообщениями между Next.js API routes и WebSocket сервером

export async function sendBalanceUpdateToWS(userId: number, balance: number, balanceStr?: string) {
  try {
    // WebSocket сервер должен слушать HTTP запросы на отдельном порту
    // или использовать Redis pub/sub для обмена сообщениями
    // Пока что просто логируем - реализация зависит от архитектуры
    
    const wsServerUrl = process.env.WS_SERVER_URL || 'http://localhost:4889';
    
    // Отправляем HTTP запрос к WebSocket серверу для уведомления
    const response = await fetch(`${wsServerUrl}/api/balance-update`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        user_id: userId,
        balance: balance,
        balanceStr: balanceStr || balance.toString(),
      }),
    });

    if (!response.ok) {
      console.error('[WS API] Failed to send balance update:', response.statusText);
    }
  } catch (error) {
    // Игнорируем ошибки - WebSocket сервер может быть недоступен
    console.error('[WS API] Error sending balance update:', error);
  }
}









