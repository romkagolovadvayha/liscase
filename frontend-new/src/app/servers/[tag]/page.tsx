import React from 'react';
import { notFound } from 'next/navigation';
import { cookies } from 'next/headers';
import type { Metadata } from 'next';
import ServerStatsClient from '@/components/servers/ServerStatsClient';
import { getServerStatsData } from '@/lib/server-stats';
import { query } from '@/lib/db';

async function getServerStats(tag: string, wipe?: string) {
  try {
    // Получаем текущего пользователя (если авторизован)
    let currentUserSteamId: string | undefined = undefined;
    try {
      const cookieStore = await cookies();
      const authToken = cookieStore.get('auth_token')?.value;
      
      if (authToken) {
        const [user] = await query<{ steam_id: string }>(`
          SELECT steam_id FROM user WHERE auth_key = ? AND status = 1 LIMIT 1
        `, [authToken]);
        if (user) {
          currentUserSteamId = user.steam_id;
        }
      }
    } catch (error) {
      console.error('Error getting current user:', error);
      // Продолжаем без информации о пользователе
    }

    const data = await getServerStatsData(tag, wipe, currentUserSteamId);
    return data;
  } catch (error) {
    console.error('Error fetching server stats:', error);
    return null;
  }
}

export async function generateMetadata({ 
  params,
  searchParams 
}: { 
  params: Promise<{ tag: string }>;
  searchParams: Promise<{ wipe?: string }>;
}): Promise<Metadata> {
  const metadataStartTime = Date.now();
  console.log(`[ServerStatsPage generateMetadata] START`);
  
  try {
    const { tag } = await params;
    const { wipe } = await searchParams;
    const dataFetchStart = Date.now();
    const data = await getServerStats(tag, wipe);
    console.log(`[ServerStatsPage generateMetadata] getServerStats took ${Date.now() - dataFetchStart}ms`);
    
    if (!data || !data.server) {
      return {
        title: 'Страница не найдена',
      };
    }

    const metadata = {
      title: `Статистика сервера ${data.server.monitoring_name || data.server.name}`,
      description: `Топы игроков на сервере ${data.server.monitoring_name || data.server.name}. Лучшие рейдеры, киллеры, фармеры и другие категории.`,
    };
    console.log(`[ServerStatsPage generateMetadata] END, total time: ${Date.now() - metadataStartTime}ms`);
    return metadata;
  } catch (error) {
    console.error(`[ServerStatsPage generateMetadata] ERROR, total time: ${Date.now() - metadataStartTime}ms:`, error);
    return {
      title: 'Ошибка загрузки',
    };
  }
}

export default async function ServerStatsPage({ 
  params,
  searchParams 
}: { 
  params: Promise<{ tag: string }>;
  searchParams: Promise<{ wipe?: string }>;
}) {
  const pageStartTime = Date.now();
  console.log(`[ServerStatsPage] START`);
  
  try {
    const paramsStart = Date.now();
    const { tag } = await params;
    const { wipe } = await searchParams;
    console.log(`[ServerStatsPage] Params parsing took ${Date.now() - paramsStart}ms, tag: ${tag}, wipe: ${wipe || 'default'}`);
    
    const dataFetchStart = Date.now();
    const data = await getServerStats(tag, wipe);
    console.log(`[ServerStatsPage] getServerStats took ${Date.now() - dataFetchStart}ms`);

    if (!data || !data.server) {
      console.error(`[ServerStatsPage] Server not found for tag: ${tag}, total time: ${Date.now() - pageStartTime}ms`, data);
      notFound();
    }

    console.log(`[ServerStatsPage] Data loaded successfully for tag: ${tag}`, {
      server: data.server.tag,
      topsCount: Object.keys(data.tops || {}).length,
      wipesCount: (data.wipes || []).length,
    });

    const renderStart = Date.now();
    const component = <ServerStatsClient initialData={data} />;
    console.log(`[ServerStatsPage] Component render took ${Date.now() - renderStart}ms, total page time: ${Date.now() - pageStartTime}ms`);
    
    return component;
  } catch (error) {
    console.error('[ServerStatsPage] Error:', error);
    notFound();
  }
}
