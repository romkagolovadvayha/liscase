'use client';

import React, { useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import { HeadsetMic } from '@mui/icons-material';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';

export default function SupportIcon() {
  const [isVisible, setIsVisible] = useState(false);
  const [hasUnread, setHasUnread] = useState(false);
  const router = useRouter();

  useEffect(() => {
    // Проверяем наличие непрочитанных сообщений только если авторизован
    const checkUnread = async () => {
      if (!isAuthenticated()) {
        return;
      }
      
      try {
        const response = await apiClient.get('/support/tickets');
        if (response.data.success) {
          const data = response.data.data;
          const hasUnreadMessages = data.tickets?.some(
            (ticket: any) => ticket.unread_count > 0
          );
          setHasUnread(hasUnreadMessages);
        }
      } catch (error) {
        console.error('Error checking unread messages:', error);
      }
    };

    // Проверяем при загрузке
    checkUnread();

    // Проверяем каждые 30 секунд
    const interval = setInterval(checkUnread, 30000);

    return () => clearInterval(interval);
  }, []);

  useEffect(() => {
    // Показываем иконку с небольшой задержкой для анимации
    const timer = setTimeout(() => setIsVisible(true), 500);
    return () => clearTimeout(timer);
  }, []);

  const handleClick = () => {
    // Проверяем авторизацию перед открытием
    if (!isAuthenticated()) {
      // Открываем событие для показа предложения авторизации
      window.dispatchEvent(new CustomEvent('openSupportAuth'));
      return;
    }
    
    // Открываем поддержку через событие
    window.dispatchEvent(new CustomEvent('openSupport'));
  };

  if (!isVisible) return null;

  return (
    <div
      className={`support-icon ${hasUnread ? 'support-icon--unread' : ''}`}
      onClick={handleClick}
      title="Поддержка"
    >
      <div className="support-icon-badge">
        <HeadsetMic style={{ color: 'var(--primary-colors-white)', fontSize: '24px' }} />
        {hasUnread && <span className="support-icon-unread-dot"></span>}
      </div>
      <div className="support-icon-pulse"></div>
    </div>
  );
}

