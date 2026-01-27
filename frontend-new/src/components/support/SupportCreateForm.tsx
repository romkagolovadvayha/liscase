'use client';

import React, { useState } from 'react';
import type { SupportTicket } from '@/types/support';
import apiClient from '@/lib/api/client';
import SupportTicketHeader from './SupportTicketHeader';
import SupportMessages from './SupportMessages';
import SupportMessageForm from './SupportMessageForm';

interface SupportCreateFormProps {
  onTicketCreated: (ticket: SupportTicket) => void;
  user?: {
    id: number;
    username: string;
    avatar: string;
    blocked_support: boolean;
    blocked_support_at: string | null;
    isAdmin?: boolean;
  } | null;
}

export default function SupportCreateForm({
  onTicketCreated,
  user,
}: SupportCreateFormProps) {
  const [isCreating, setIsCreating] = useState(false);

  // Проверяем, заблокирован ли пользователь
  const isBlocked =
    user?.blocked_support ||
    (user?.blocked_support_at && new Date(user.blocked_support_at) > new Date());

  // Создаем фиктивный тикет для отображения заголовка
  // Используем специальный номер, чтобы в заголовке отображалось "Новый тикет"
  const fakeTicket: SupportTicket = {
    id: 0,
    number: -1, // Специальное значение для нового тикета
    user_id: user?.id || 0,
    server_tag: null,
    status: 'open',
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
    user: user ? {
      id: user.id,
      username: user.username,
      avatar: user.avatar,
      blocked_support: user.blocked_support,
      blocked_support_at: user.blocked_support_at,
    } : undefined,
    unread_count: 0,
  };

  const handleSendMessage = async (message: string, files?: File[]) => {
    if (isCreating) return;
    
    // Проверяем, есть ли стикеры в сообщении (тег <img class="support_sticker")
    const hasStickers = message && (
      message.includes('class="support_sticker"') || 
      message.includes("class='support_sticker'")
    );
    
    // Проверяем, что есть либо сообщение (включая стикеры), либо файлы
    if (!message?.trim() && !hasStickers && (!files || files.length === 0)) {
      return;
    }
    
    setIsCreating(true);
    try {
      // Создаем тикет с первым сообщением
      const formData = new FormData();
      if (message?.trim() || hasStickers) {
        formData.append('message', message || '');
      }
      
      if (files && files.length > 0) {
        files.forEach((file) => {
          formData.append('files[]', file);
        });
      }

      const response = await apiClient.post('/support/tickets/create', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      if (response.data?.success && response.data?.data?.ticket) {
        // Тикет успешно создан, передаем его в родительский компонент
        onTicketCreated(response.data.data.ticket);
      } else {
        console.error('Unexpected response format:', response.data);
        throw new Error('Не удалось создать тикет. Неверный формат ответа.');
      }
    } catch (error: any) {
      console.error('Error creating ticket:', error);
      const errorMessage = error?.response?.data?.message || error?.message || 'Не удалось создать тикет';
      alert(errorMessage);
    } finally {
      setIsCreating(false);
    }
  };

  if (isBlocked) {
    return (
      <>
        <SupportTicketHeader
          ticket={fakeTicket}
          user={user ? { ...user, isAdmin: user.isAdmin || false } : null}
          ticketUser={user ? {
            id: user.id,
            username: user.username,
            blocked_support: user.blocked_support,
            blocked_support_at: user.blocked_support_at,
          } : {
            id: 0,
            username: 'Пользователь',
            blocked_support: false,
            blocked_support_at: null,
          }}
          onClose={() => {}}
          onOpen={() => {}}
        />
        <div className="support-messages-wrap">
          <div className="support-messages">
            <div className="support-blocked-message">
              <p>
                Доступ в чат будет разблокирован{' '}
                {user?.blocked_support_at && (
                  <span>
                    {new Date(user.blocked_support_at).toLocaleString('ru-RU')}
                  </span>
                )}
              </p>
            </div>
          </div>
        </div>
      </>
    );
  }

  return (
    <>
      <SupportTicketHeader
        ticket={fakeTicket}
        user={user ? { ...user, isAdmin: user.isAdmin || false } : null}
        ticketUser={user ? {
          id: user.id,
          username: user.username,
          blocked_support: user.blocked_support,
          blocked_support_at: user.blocked_support_at,
        } : {
          id: 0,
          username: 'Пользователь',
          blocked_support: false,
          blocked_support_at: null,
        }}
        onClose={() => {}}
        onOpen={() => {}}
      />
      <SupportMessages
        messages={[]}
        currentUserId={user?.id || 0}
        isAdmin={false}
        showWelcomeMessage={true}
      />
      <SupportMessageForm
        onSend={handleSendMessage}
        disabled={isCreating}
        ticketId={0}
      />
    </>
  );
}
