'use client';

import React from 'react';
import Sidebar from './Sidebar';
import { useServersData } from '@/hooks/useServersData';

export default function SidebarClient() {
  const { data: serversData, isLoading } = useServersData();

  // Sidebar будет рендериться всегда, но ServersList внутри будет показывать данные только если они есть
  return (
    <Sidebar
      servers={serversData?.servers}
      projectStats={serversData?.projectStats}
      serversLoading={isLoading}
    />
  );
}

