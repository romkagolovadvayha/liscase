'use client';

import { useEffect, useRef, useState, useCallback } from 'react';
import { useNotificationWebSocket } from '@/providers/NotificationWebSocketProvider';
import type { SupportMessage } from '@/types/support';
import apiClient from '@/lib/api/client';

interface UseSupportWebSocketOptions {
  ticketId?: number;
  enabled?: boolean;
  onMessage?: (message: SupportMessage) => void;
  onStatusUpdate?: (status: string) => void;
  onTyping?: (userId: number, typing: boolean) => void;
}

export function useSupportWebSocket({
  ticketId,
  enabled = true,
  onMessage,
  onStatusUpdate,
  onTyping,
}: UseSupportWebSocketOptions = {}) {
  const [status, setStatus] = useState<string>('open');
  const typingUsersRef = useRef<Set<number>>(new Set());
  const lastMessageIdRef = useRef<number | null>(null);
  const typingTimeoutRef = useRef<NodeJS.Timeout | null>(null);

  // Используем глобальное WebSocket подключение
  const { isConnected, registerTicketHandlers, sendTyping: wsSendTyping } = useNotificationWebSocket();

  // Сохраняем обработчики в ref, чтобы они не вызывали переподписку
  const onMessageRef = useRef(onMessage);
  const onStatusUpdateRef = useRef(onStatusUpdate);
  const onTypingRef = useRef(onTyping);
  
  // Обновляем refs при изменении обработчиков
  useEffect(() => {
    onMessageRef.current = onMessage;
    onStatusUpdateRef.current = onStatusUpdate;
    onTypingRef.current = onTyping;
  }, [onMessage, onStatusUpdate, onTyping]);

  // Загрузка нового сообщения по messageId (использует ref, чтобы не вызывать переподписку)
  const loadMessage = useCallback(async (messageId: number, ticketNumber: number) => {
    try {
      // Загружаем все сообщения тикета (endpoint для одного сообщения не реализован)
      const response = await apiClient.get(`/support/tickets/${ticketNumber}`);
      if (response.data.success && response.data.data?.messages) {
        const messages = response.data.data.messages as SupportMessage[];
        // Находим новое сообщение
        const newMessage = messages.find(msg => msg.id === messageId);
        if (newMessage && onMessageRef.current) {
          onMessageRef.current(newMessage);
        }
      }
    } catch (error) {
      console.error('[useSupportWebSocket] Error loading message:', error);
    }
  }, []);

  // Регистрация обработчиков для тикета (сообщения приходят автоматически от сервера)
  useEffect(() => {
    if (!enabled || !ticketId || !isConnected) {
      return;
    }

    const unregister = registerTicketHandlers(ticketId, {
      onMessage: (messageId: number, userId: number) => {
        // Проверяем, не загружали ли мы уже это сообщение
        if (lastMessageIdRef.current !== messageId) {
          lastMessageIdRef.current = messageId;
          loadMessage(messageId, ticketId);
        }
      },
      onStatus: (newStatus: string) => {
        setStatus(newStatus);
        if (onStatusUpdateRef.current) {
          onStatusUpdateRef.current(newStatus);
        }
      },
      onTyping: (userId: number, typing: boolean) => {
        if (typing) {
          typingUsersRef.current.add(userId);
        } else {
          typingUsersRef.current.delete(userId);
        }
        if (onTypingRef.current) {
          onTypingRef.current(userId, typing);
        }
      },
    });

    return unregister;
  }, [enabled, ticketId, isConnected, registerTicketHandlers, loadMessage]);

  // Сброс lastMessageId при изменении ticketId
  useEffect(() => {
    lastMessageIdRef.current = null;
  }, [ticketId]);

  // Отправка typing индикатора
  const sendTyping = useCallback((typing: boolean) => {
    if (!isConnected || !ticketId) {
      return;
    }

    // Очищаем предыдущий timeout
    if (typingTimeoutRef.current) {
      clearTimeout(typingTimeoutRef.current);
      typingTimeoutRef.current = null;
    }

    // Отправляем команду typing
    wsSendTyping(ticketId, typing);

    // Если начали печатать, автоматически отправляем false через 3 секунды
    if (typing) {
      typingTimeoutRef.current = setTimeout(() => {
        if (ticketId) {
          wsSendTyping(ticketId, false);
        }
      }, 3000);
    }
  }, [isConnected, ticketId, wsSendTyping]);

  // Очистка timeout при размонтировании
  useEffect(() => {
    return () => {
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }
    };
  }, []);

  return {
    isConnected,
    status,
    sendTyping,
    typingUsers: Array.from(typingUsersRef.current),
  };
}
