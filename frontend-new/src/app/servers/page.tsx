import React from 'react';
import ServersClient from '@/components/servers/ServersClient';
import { query } from '@/lib/db';

async function getServersData() {
  try {
    // Получаем статистику проекта (как в старой версии)
    // users - количество пользователей из таблицы user
    const [usersCount] = await query<{ count: number }>(`
      SELECT COUNT(*) as count
      FROM user
    `);

    // online - сумма players + joined из серверов со статусом 1
    const [onlineStats] = await query<{ online: number }>(`
      SELECT SUM(players + joined) as online
      FROM servers
      WHERE status = 1
    `);

    // count - количество серверов (не закрытых)
    const [serversCount] = await query<{ count: number }>(`
      SELECT COUNT(*) as count
      FROM servers
      WHERE status NOT IN (0)
    `);

    return {
      projectStats: {
        online: onlineStats?.online || 0,
        users: usersCount?.count || 0,
        count: serversCount?.count || 0,
      },
    };
  } catch (error) {
    console.error('Error fetching servers data:', error);
    return {
      projectStats: {
        online: 0,
        users: 0,
        count: 0,
      },
    };
  }
}

export const metadata = {
  title: 'Сервера Rust для комфортной игры',
  description: 'На нашем проекте работают несколько серверов Rust с разными параметрами. Выберите сервер по пингу, размеру карты и лимиту команды.',
};

export default async function ServersPage() {
  const { projectStats } = await getServersData();

  return <ServersClient projectStats={projectStats} />;
}

