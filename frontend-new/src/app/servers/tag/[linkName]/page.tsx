import React from 'react';
import type { Metadata } from 'next';
import ServerTagClient from '@/components/servers/ServerTagClient';

export function generateMetadata(): Metadata {
  return {
    title: 'Серверы по тегам',
    description: 'Список серверов по категориям.',
  };
}

export default function ServerTagPage() {
  return <ServerTagClient />;
}

