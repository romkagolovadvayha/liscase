'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import { isAuthenticated, getMe } from '@/lib/api/auth';
import { useServersData } from '@/hooks/useServersData';

/**
 * Компонент для динамической ссылки на статистику
 * Определяет сервер пользователя или первый активный сервер
 */
export default function StatsLink({ children, className }: { children: React.ReactNode; className?: string }) {
  const [href, setHref] = useState('/servers');
  
  // Используем React Query для получения данных серверов (кэшируется, предотвращает дублирование запросов)
  const { data: serversData } = useServersData();
  const servers = serversData?.servers || [];

  useEffect(() => {
    const fetchServerTag = async () => {
      try {
        // Проверяем авторизацию перед вызовом защищенного endpoint
        if (isAuthenticated()) {
          try {
            const user = await getMe();
            if (user?.server_tag && servers.length > 0) {
              // Ищем сервер по тегу из кэшированных данных
              const server = servers.find((s: any) => s.tag === user.server_tag);
              if (server) {
                setHref(`/servers/${server.tag}`);
                return;
              }
            }
          } catch (error) {
            // Если ошибка авторизации, продолжаем к публичным серверам
            console.error('Error fetching user data:', error);
          }
        }

        // Если нет пользователя или сервера, берем первый активный сервер из кэшированных данных
        if (servers.length > 0) {
          const firstServer = servers.find((s: any) => s.status === 1 || s.status === 0);
          if (firstServer) {
            setHref(`/servers/${firstServer.tag}`);
          }
        }
      } catch (error) {
        console.error('Error fetching server tag for stats:', error);
      }
    };

    if (servers.length > 0) {
      fetchServerTag();
    }
  }, [servers]);

  return (
    <Link href={href} className={className}>
      {children}
    </Link>
  );
}

