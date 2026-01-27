import React from 'react';
import type { Metadata } from 'next';
import ServerStatsClient from '@/components/servers/ServerStatsClient';

export function generateMetadata(): Metadata {
  return {
    title: 'Статистика сервера',
    description: 'Топы игроков на сервере. Лучшие рейдеры, киллеры, фармеры и другие категории.',
  };
}

export default function ServerStatsPage() {
  return <ServerStatsClient />;
}
