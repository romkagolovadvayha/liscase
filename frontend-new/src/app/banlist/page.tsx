import React from 'react';
import BanlistClient from '@/components/banlist/BanlistClient';
import { query } from '@/lib/db';
import type { BanlistResponse } from '@/hooks/useBanlistData';

async function getBanlistData() {
  try {
    // Получаем список серверов для фильтра
    const servers = await query<any>(`
      SELECT 
        id,
        monitoring_name,
        tag
      FROM servers
      WHERE status IN (1, 2)
      ORDER BY sort ASC, id ASC
    `);

    return {
      servers: servers.map((s: any) => ({
        id: s.id,
        name: s.monitoring_name,
        tag: s.tag,
      })),
    };
  } catch (error) {
    console.error('Error fetching banlist data:', error);
    return {
      servers: [],
    };
  }
}

async function getInitialBanlistData(): Promise<BanlistResponse | undefined> {
  try {
    const response = await fetch(`${process.env.NEXT_PUBLIC_SITE_URL || 'http://localhost:3000'}/api/banlist`, {
      cache: 'no-store',
    });
    if (response.ok) {
      return response.json();
    }
  } catch (error) {
    console.error('Error fetching initial banlist data:', error);
  }
  return undefined;
}

export const metadata = {
  title: 'Бан лист серверов',
  description: 'Общий бан-лист серверов. Проверяйте причину бана, сервер и сроки. Список обновляется автоматически.',
};

export default async function BanlistPage() {
  const { servers } = await getBanlistData();
  // Получаем начальные данные для SSR и передачи в React Query
  const initialData = await getInitialBanlistData();

  return <BanlistClient servers={servers} initialData={initialData} />;
}










