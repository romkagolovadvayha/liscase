'use client';

import React, { useState, useEffect, useRef } from 'react';
import { useSupport } from './SupportProvider';
import type { SupportTicket, SupportMessage } from '@/types/support';
import SupportMessages from './SupportMessages';
import SupportMessageForm from './SupportMessageForm';
import SupportTicketHeader from './SupportTicketHeader';
import SupportUserTicketsHistory from './SupportUserTicketsHistory';
import { useSupportWebSocket } from '@/hooks/useSupportWebSocket';
import apiClient from '@/lib/api/client';
import { useSettings } from '@/hooks/useSettings';
import { getDefaultAvatar } from '@/lib/utils/settingsImage';

interface SupportTicketClientProps {
  initialData: {
    ticket: SupportTicket;
    messages: SupportMessage[];
    tickets: SupportTicket[];
    reports?: Array<{
      id: number;
      user: {
        id: number;
        username: string;
        steam_id: string;
        avatar?: string;
      };
      reason: string;
      created_at: string;
    }>;
    user: {
      id: number;
      username: string;
      avatar: string;
      blocked_support: boolean;
      blocked_support_at: string | null;
      isAdmin: boolean;
    } | null;
  };
  ticketNumber: number;
  onTicketUpdated?: () => void;
}

export default function SupportTicketClient({
  initialData,
  ticketNumber,
  onTicketUpdated,
}: SupportTicketClientProps) {
  console.log('[SupportTicketClient] Component rendered with initialData.tickets:', initialData?.tickets, 'count:', initialData?.tickets?.length || 0);
  const { openSupport } = useSupport();
  const { data: settings } = useSettings();
  const defaultAvatar = getDefaultAvatar(settings);
  
  // Создаем fallback для ticket если его нет
  const defaultTicket: SupportTicket = {
    id: 0,
    number: ticketNumber,
    user_id: 0,
    server_tag: null,
    status: 'open',
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
    user: {
      id: 0,
      username: 'Unknown',
      avatar: defaultAvatar,
      blocked_support: false,
      blocked_support_at: null,
    },
  };
  console.log('[SupportTicketClient] Default ticket:', initialData);
  const [ticket, setTicket] = useState(initialData.ticket || defaultTicket);
  const [messages, setMessages] = useState<SupportMessage[]>(initialData.messages || []);
  const [showUserTicketsHistory, setShowUserTicketsHistory] = useState(false);
  console.log('[SupportTicketClient] State initialized');
  const [ticketUser, setTicketUser] = useState<{
    id: number;
    username: string;
    blocked_support: boolean;
    blocked_support_at: string | null;
    avatar?: string;
    status?: number;
    steam_id?: string;
    server?: any;
    trade_link?: string;
  }>(
    (initialData.ticket?.user && typeof initialData.ticket.user === 'object') 
      ? {
          id: initialData.ticket.user.id,
          username: initialData.ticket.user.username,
          blocked_support: initialData.ticket.user.blocked_support ?? false,
          blocked_support_at: initialData.ticket.user.blocked_support_at ?? null,
          avatar: initialData.ticket.user.avatar,
        }
      : {
          id: initialData.ticket?.user_id || ticket.user_id || 0,
          username: 'Unknown',
          blocked_support: false,
          blocked_support_at: null,
          status: 1,
        }
  );
  
  // Состояние для редактирования сообщения
  const [editingMessageId, setEditingMessageId] = useState<number | null>(null);
  const [editingMessageText, setEditingMessageText] = useState<string>('');
  
  const messagesEndRef = useRef<HTMLDivElement>(null);

  // WebSocket подключение для real-time обновлений
  const { sendTyping } = useSupportWebSocket({
    ticketId: ticketNumber,
    enabled: !!ticketNumber && !!ticket.id,
    onMessage: (newMessage: SupportMessage) => {
      // Добавляем новое сообщение в список, проверяя на дубликаты
      setMessages((prevMessages) => {
        // Проверяем, нет ли уже такого сообщения
        const exists = prevMessages.some(msg => msg.id === newMessage.id);
        if (exists) {
          return prevMessages;
        }
        // Добавляем новое сообщение
        return [...prevMessages, newMessage];
      });
    },
    onStatusUpdate: (newStatus: string) => {
      // Обновляем статус тикета
      setTicket((prev) => ({ ...prev, status: newStatus as 'open' | 'closed' }));
      onTicketUpdated?.();
    },
  });

  // Функция загрузки данных тикета
  const loadTicketData = async () => {
    if (!ticketNumber) return;
    
    try {
      const response = await apiClient.get(`/support/tickets/${ticketNumber}`);
      if (response.data.success) {
        const data = response.data.data;
        if (data.ticket) {
          setTicket(data.ticket);
          if (data.ticket.user && typeof data.ticket.user === 'object') {
            console.log('[SupportTicketClient] Setting ticketUser:', data.ticket.user);
            setTicketUser({
              id: data.ticket.user.id,
              username: data.ticket.user.username,
              blocked_support: data.ticket.user.blocked_support ?? false,
              blocked_support_at: data.ticket.user.blocked_support_at ?? null,
              avatar: data.ticket.user.avatar,
            });
          }
        }
        if (data.messages) {
          setMessages(data.messages);
        } else {
          setMessages([]);
        }
      }
    } catch (error) {
      console.error('Error loading ticket data:', error);
    }
  };

  // Загружаем данные тикета при изменении ticketNumber
  useEffect(() => {
    if (ticketNumber) {
      loadTicketData();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ticketNumber]);

  // Обновляем состояние при изменении initialData (только при первой загрузке, если данные уже есть)
  useEffect(() => {
    if (initialData.ticket && !ticket.id) {
      setTicket(initialData.ticket);
      if (initialData.ticket.user && typeof initialData.ticket.user === 'object') {
        setTicketUser({
          id: initialData.ticket.user.id,
          username: initialData.ticket.user.username,
          blocked_support: initialData.ticket.user.blocked_support ?? false,
          blocked_support_at: initialData.ticket.user.blocked_support_at ?? null,
          avatar: initialData.ticket.user.avatar,
        });
      }
    }
    // Не перезаписываем messages из initialData, так как они загружаются через API
  }, [initialData]);

  // Слушаем уведомления о создании/изменении статуса тикетов для обновления списка в родительском компоненте
  useEffect(() => {
    const handleTicketCreated = () => {
      console.log('[SupportTicketClient] Ticket created notification received, dispatching event to parent');
      // Отправляем событие родительскому компоненту для обновления списка тикетов
      window.dispatchEvent(new CustomEvent('support-ticket-list-needs-refresh'));
    };

    const handleTicketStatusUpdated = (event: Event) => {
      const customEvent = event as CustomEvent<{ ticketId: number; status: string }>;
      // Обновляем статус только если это текущий тикет
      if (customEvent.detail.ticketId === ticketNumber) {
        console.log('[SupportTicketClient] Ticket status updated notification received for current ticket');
        // Обновляем статус локально, не перезагружая весь список
        setTicket((prev) => ({ ...prev, status: customEvent.detail.status as 'open' | 'closed' }));
        // Отправляем событие родительскому компоненту для обновления только этого тикета в списке
        window.dispatchEvent(new CustomEvent('support-ticket-updated', {
          detail: { ticketId: ticketNumber, status: customEvent.detail.status }
        }));
      } else {
        // Если это другой тикет, обновляем только его в списке
        window.dispatchEvent(new CustomEvent('support-ticket-updated', {
          detail: { ticketId: customEvent.detail.ticketId, status: customEvent.detail.status }
        }));
      }
    };

    const handleMessageUpdated = async (event: Event) => {
      const customEvent = event as CustomEvent<{ ticketId: number; messageId: number }>;
      // Обновляем сообщение только если оно относится к текущему тикету
      if (customEvent.detail.ticketId === ticketNumber) {
        try {
          const response = await apiClient.get(`/support/tickets/${ticketNumber}`);
          if (response.data.success && response.data.data?.messages) {
            const messages = response.data.data.messages as SupportMessage[];
            setMessages(messages);
          }
        } catch (error) {
          console.error('Error reloading messages after update:', error);
        }
      }
    };

    const handleMessageDeleted = (event: Event) => {
      const customEvent = event as CustomEvent<{ ticketId: number; messageId: number | string }>;
      const messageId = typeof customEvent.detail.messageId === 'string' 
        ? parseInt(customEvent.detail.messageId, 10) 
        : customEvent.detail.messageId;
      const ticketId = typeof customEvent.detail.ticketId === 'string'
        ? parseInt(customEvent.detail.ticketId, 10)
        : customEvent.detail.ticketId;
      
      console.log('[SupportTicketClient] Message deleted notification received:', {
        ticketId,
        messageId,
        originalTicketId: customEvent.detail.ticketId,
        originalMessageId: customEvent.detail.messageId,
        currentTicketNumber: ticketNumber
      });
      // Удаляем сообщение только если оно относится к текущему тикету
      if (ticketId === ticketNumber) {
        console.log('[SupportTicketClient] Removing message from list:', messageId);
        setMessages((prev) => {
          const filtered = prev.filter((msg) => msg.id !== messageId);
          console.log('[SupportTicketClient] Messages after filter:', {
            before: prev.length,
            after: filtered.length,
            removedMessageId: messageId,
            messageIds: prev.map(m => m.id)
          });
          return filtered;
        });
      } else {
        console.log('[SupportTicketClient] Ticket ID mismatch, ignoring deletion:', {
          receivedTicketId: ticketId,
          currentTicketNumber: ticketNumber
        });
      }
    };

    window.addEventListener('support-ticket-created', handleTicketCreated);
    window.addEventListener('support-ticket-status-updated', handleTicketStatusUpdated);
    window.addEventListener('support-message-updated', handleMessageUpdated);
    window.addEventListener('support-message-deleted', handleMessageDeleted);

    return () => {
      window.removeEventListener('support-ticket-created', handleTicketCreated);
      window.removeEventListener('support-ticket-status-updated', handleTicketStatusUpdated);
      window.removeEventListener('support-message-updated', handleMessageUpdated);
      window.removeEventListener('support-message-deleted', handleMessageDeleted);
    };
  }, [ticketNumber]); // Добавляем ticketNumber в зависимости

  // Автоскролл обрабатывается в SupportMessages компоненте

  const handleSendMessage = async (message: string, files?: File[]) => {
    // Если мы в режиме редактирования, отправляем PATCH запрос
    if (editingMessageId !== null) {
      try {
        // Для редактирования отправляем JSON, так как файлы не нужны
        const response = await apiClient.patch(`/support/tickets/${ticketNumber}/messages/${editingMessageId}`, {
          message: message || '',
        });

        if (response.data.success) {
          // Обновляем сообщение в списке
          setMessages((prev) =>
            prev.map((msg) => (msg.id === editingMessageId ? response.data.data.message : msg))
          );
          // Сбрасываем режим редактирования
          setEditingMessageId(null);
          setEditingMessageText('');
        }
      } catch (error) {
        console.error('Error editing message:', error);
        alert('Ошибка при редактировании сообщения');
        throw error;
      }
      return;
    }

    // Обычная отправка нового сообщения
    try {
      const formData = new FormData();
      formData.append('message', message || '');
      
      // Добавляем файлы
      if (files && files.length > 0) {
        files.forEach((file) => {
          formData.append('files[]', file, file.name);
        });
      }

      // Отправляем сообщение с файлами
      const response = await apiClient.post(`/support/tickets/${ticketNumber}/messages`, formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      if (response.data.success) {
        setMessages((prev) => [...prev, response.data.data.message]);
      }
    } catch (error) {
      console.error('Error sending message:', error);
      // TODO: Показать toast уведомление об ошибке
      throw error;
    }
  };

  const handleCloseTicket = async () => {
    try {
      const response = await apiClient.post(`/support/tickets/${ticketNumber}/close`);

      if (response.data.success) {
        setTicket((prev) => ({ ...prev, status: 'closed' }));
        onTicketUpdated?.();
      }
    } catch (error) {
      console.error('Error closing ticket:', error);
    }
  };

  const handleOpenTicket = async () => {
    try {
      const response = await apiClient.post(`/support/tickets/${ticketNumber}/open`);

      if (response.data.success) {
        setTicket((prev) => ({ ...prev, status: 'open' }));
        onTicketUpdated?.();
      }
    } catch (error) {
      console.error('Error opening ticket:', error);
    }
  };

  const handleDeleteMessage = async (messageId: number) => {
    try {
      const response = await apiClient.delete(`/support/tickets/${ticketNumber}/messages/${messageId}`);

      if (response.data.success) {
        // Удаляем сообщение из списка
        setMessages((prev) => prev.filter((msg) => msg.id !== messageId));
      }
    } catch (error) {
      console.error('Error deleting message:', error);
      // TODO: Показать toast уведомление об ошибке
    }
  };

  const handleEditMessage = (messageId: number, currentMessage: string) => {
    // Устанавливаем режим редактирования
    setEditingMessageId(messageId);
    setEditingMessageText(currentMessage);
  };

  const handleCancelEdit = () => {
    setEditingMessageId(null);
    setEditingMessageText('');
  };

  const handleMute = async (userId: number, blocked: boolean) => {
    try {
      const response = await apiClient.post(`/support/users/${userId}/mute`, {
        blocked: blocked,
        ticketNumber: ticketNumber, // Передаем номер тикета для создания системного сообщения
      });

      if (response.data.success) {
        // Обновляем данные пользователя тикета в модальном окне
        const updateResponse = await apiClient.get(`/support/tickets/${ticketNumber}`);
        if (updateResponse.data.success) {
          const data = updateResponse.data.data;
          if (data.ticket?.user) {
            setTicketUser((prev) => ({
              ...prev,
              blocked_support_at: data.ticket.user.blocked_support_at ?? null,
            }));
          }
        }
        onTicketUpdated?.();
      }
    } catch (error) {
      console.error('Error muting user:', error);
      throw error;
    }
  };

  const handleBlockChat = async (userId: number, blocked: boolean) => {
    try {
      const response = await apiClient.post(`/support/users/${userId}/block-chat`, {
        blocked: blocked,
        ticketNumber: ticketNumber, // Передаем номер тикета для создания системного сообщения
      });

      if (response.data.success) {
        // Обновляем данные пользователя тикета в модальном окне
        const updateResponse = await apiClient.get(`/support/tickets/${ticketNumber}`);
        if (updateResponse.data.success) {
          const data = updateResponse.data.data;
          if (data.ticket?.user) {
            setTicketUser((prev) => ({
              ...prev,
              blocked_support: data.ticket.user.blocked_support ?? false,
            }));
          }
        }
        onTicketUpdated?.();
      }
    } catch (error) {
      console.error('Error blocking chat:', error);
      throw error;
    }
  };

  const handleBlockAccount = async (userId: number, blocked: boolean) => {
    try {
      const response = await apiClient.post(`/support/users/${userId}/block-account`, {
        blocked: blocked,
        ticketNumber: ticketNumber, // Передаем номер тикета для создания системного сообщения
      });

      if (response.data.success) {
        // Обновляем данные пользователя тикета в модальном окне
        const updateResponse = await apiClient.get(`/support/tickets/${ticketNumber}`);
        if (updateResponse.data.success) {
          const data = updateResponse.data.data;
          if (data.ticket?.user) {
            setTicketUser((prev) => ({
              ...prev,
              status: data.ticket.user.status,
            }));
          }
        }
        onTicketUpdated?.();
      }
    } catch (error) {
      console.error('Error blocking account:', error);
      throw error;
    }
  };

  const isBlocked =
    initialData.user?.blocked_support ||
    (initialData.user?.blocked_support_at &&
      initialData.user.blocked_support_at &&
      new Date(initialData.user.blocked_support_at) > new Date());

  // Если ticket не загружен или невалиден, показываем загрузку
  if (!ticket || !ticket.id) {
    return (
      <div className="support-messages-empty">
        <p>Загрузка тикета...</p>
      </div>
    );
  }

  return (
    <>
      <SupportTicketHeader
        ticket={ticket}
        user={initialData.user}
        ticketUser={ticketUser}
        onClose={handleCloseTicket}
        onOpen={handleOpenTicket}
        onMute={handleMute}
        onBlockChat={handleBlockChat}
        onBlockAccount={handleBlockAccount}
        onShowUserTicketsHistory={initialData.user?.isAdmin ? () => setShowUserTicketsHistory(!showUserTicketsHistory) : undefined}
      />
      {(() => {
        console.log('[SupportTicketClient] Rendering history check - showUserTicketsHistory:', showUserTicketsHistory, 'isAdmin:', initialData.user?.isAdmin, 'ticketUser:', ticketUser);
        return null;
      })()}
      {showUserTicketsHistory && initialData.user?.isAdmin && ticketUser && (
        <SupportUserTicketsHistory
          userId={ticketUser.id}
          currentTicketId={ticket.id}
          isAdmin={initialData.user.isAdmin}
          onClose={() => {
            console.log('[SupportTicketClient] Closing history');
            setShowUserTicketsHistory(false);
          }}
        />
      )}
      <SupportMessages
        messages={messages}
        currentUserId={initialData.user?.id || 0}
        isAdmin={initialData.user?.isAdmin || false}
        onDeleteMessage={handleDeleteMessage}
        onEditMessage={handleEditMessage}
        ticketUser={ticketUser ? {
          id: ticketUser.id,
          username: ticketUser.username,
          steam_id: (ticketUser as any).steam_id,
          server: (ticketUser as any).server,
          trade_link: (ticketUser as any).trade_link,
        } : undefined}
        reports={initialData.reports}
      />
      {ticket.status === 'open' ? (
        !isBlocked ? (
          <SupportMessageForm
            onSend={handleSendMessage}
            disabled={false}
            ticketId={ticketNumber}
            onTyping={sendTyping}
            editingMessageId={editingMessageId}
            editingMessageText={editingMessageText}
            onCancelEdit={handleCancelEdit}
          />
        ) : (
          <div className="support-blocked-message">
            <p>
              Доступ в чат будет разблокирован{' '}
              {initialData.user?.blocked_support_at && (
                <span>
                  {new Date(initialData.user.blocked_support_at).toLocaleString('ru-RU')}
                </span>
              )}
            </p>
          </div>
        )
      ) : (
        <div className="support-closed-message">
          <p>Тикет закрыт. Нельзя отправлять сообщения в закрытые тикеты.</p>
        </div>
      )}
      <div ref={messagesEndRef} />
    </>
  );
}

