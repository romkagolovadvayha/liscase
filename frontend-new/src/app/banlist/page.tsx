'use client';

import React from 'react';
import BanlistClient from '@/components/banlist/BanlistClient';
import { useServersData } from '@/hooks/useServersData';

export default function BanlistPage() {
  const { data: serversData } = useServersData();
  
  const servers = serversData?.servers?.map(server => ({
    id: server.id,
    name: server.name,
    tag: server.tag,
  })) || [];

  return <BanlistClient servers={servers} />;
}










