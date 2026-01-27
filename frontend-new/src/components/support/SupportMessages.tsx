'use client';

import React, { useEffect, useRef } from 'react';
import type { SupportMessage } from '@/types/support';
import SupportMessageItem from './SupportMessageItem';

interface SupportMessagesProps {
  messages: SupportMessage[];
  currentUserId: number;
  isAdmin?: boolean;
  onDeleteMessage?: (messageId: number) => void;
  onEditMessage?: (messageId: number, currentMessage: string) => void;
  ticketUser?: {
    id: number;
    username: string;
    steam_id?: string;
    server?: {
      id: number;
      name: string;
      tag: string;
    };
    trade_link?: string;
  };
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
  showWelcomeMessage?: boolean;
}

export default function SupportMessages({
  messages,
  currentUserId,
  isAdmin = false,
  onDeleteMessage,
  onEditMessage,
  ticketUser,
  reports = [],
  showWelcomeMessage = false,
}: SupportMessagesProps) {
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const messagesContainerRef = useRef<HTMLDivElement>(null);

  // Автоскролл к последнему сообщению при открытии и при новых сообщениях
  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    // Проверяем, есть ли прокрутка
    if (messagesContainerRef.current) {
      const hasScroll = messagesContainerRef.current.scrollHeight > messagesContainerRef.current.clientHeight;
      if (hasScroll) {
        messagesContainerRef.current.classList.add('has-scroll');
      } else {
        messagesContainerRef.current.classList.remove('has-scroll');
      }
    }
  }, [messages]);

  useEffect(() => {
    // Автоскролл при открытии и при новых сообщениях
    scrollToBottom();
  }, [messages]);

  // Функция для форматирования даты
  const formatDate = (date: Date): string => {
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);
    
    const messageDate = new Date(date);
    messageDate.setHours(0, 0, 0, 0);
    today.setHours(0, 0, 0, 0);
    yesterday.setHours(0, 0, 0, 0);
    
    if (messageDate.getTime() === today.getTime()) {
      return 'Сегодня';
    } else if (messageDate.getTime() === yesterday.getTime()) {
      return 'Вчера';
    } else {
      return messageDate.toLocaleDateString('ru-RU', { 
        day: 'numeric', 
        month: 'long',
        year: messageDate.getFullYear() !== today.getFullYear() ? 'numeric' : undefined
      });
    }
  };

  // Проверяем, нужно ли показывать дату
  const shouldShowDate = (message: SupportMessage, prevMessage: SupportMessage | null): boolean => {
    if (!prevMessage) return true;
    
    const currentDate = new Date(message.created_at);
    const prevDate = new Date(prevMessage.created_at);
    
    currentDate.setHours(0, 0, 0, 0);
    prevDate.setHours(0, 0, 0, 0);
    
    return currentDate.getTime() !== prevDate.getTime();
  };

  return (
    <div className="support-messages-wrap">
      <div ref={messagesContainerRef} className="support-messages">
        {messages.length === 0 ? (
          showWelcomeMessage ? (
            <div className="support-messages-welcome">
              <div className="support-messages-welcome-content">
                <h3>Добро пожаловать в поддержку!</h3>
                <p>Опишите вашу проблему или вопрос, и мы обязательно вам поможем.</p>
              </div>
            </div>
          ) : (
            <div className="support-messages-empty">
              <p>Нет сообщений</p>
            </div>
          )
        ) : (
          <>
            {messages.map((message, index) => {
              const prevMessage = index > 0 ? messages[index - 1] : null;
              // Показываем аватар если:
              // 1. Это первое сообщение
              // 2. Предыдущее сообщение от другого пользователя
              // 3. У предыдущего сообщения нет пользователя
              const showAvatar = !prevMessage || 
                (prevMessage.user?.id !== message.user?.id) || 
                !prevMessage.user || 
                !message.user;
              const showDate = shouldShowDate(message, prevMessage);
              
              return (
                <React.Fragment key={message.id}>
                  {showDate && (
                    <div className="support-message-date-divider">
                      <span>{formatDate(new Date(message.created_at))}</span>
                    </div>
                  )}
                  <SupportMessageItem
                    message={message}
                    isOwn={message.user_id === currentUserId}
                    isAdmin={isAdmin}
                    onDelete={onDeleteMessage}
                    onEdit={onEditMessage}
                    ticketUser={ticketUser}
                    reports={reports}
                    showAvatar={showAvatar}
                  />
                </React.Fragment>
              );
            })}
            <div ref={messagesEndRef} />
          </>
        )}
      </div>
    </div>
  );
}

