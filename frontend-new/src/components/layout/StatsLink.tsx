'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';

/**
 * Компонент для динамической ссылки на статистику
 * Определяет сервер пользователя или первый активный сервер
 */
export default function StatsLink({ children, className }: { children: React.ReactNode; className?: string }) {
  const [href, setHref] = useState('/servers');

  useEffect(() => {
    const fetchServerTag = async () => {
      try {
        const response = await fetch('/api/auth/me');
        const result = await response.json();

        if (result.success && result.data?.server_tag) {
          setHref(`/servers/${result.data.server_tag}`);
        } else {
          // Если нет пользователя или сервера, получаем первый активный сервер
          const serversResponse = await fetch('/api/servers');
          const serversResult = await serversResponse.json();
          
          if (serversResult.success && serversResult.data?.length > 0) {
            const firstServer = serversResult.data.find((s: any) => s.status === 1 || s.status === 0);
            if (firstServer) {
              setHref(`/servers/${firstServer.tag}`);
            }
          }
        }
      } catch (error) {
        console.error('Error fetching server tag for stats:', error);
      }
    };

    fetchServerTag();
  }, []);

  return (
    <Link href={href} className={className}>
      {children}
    </Link>
  );
}

