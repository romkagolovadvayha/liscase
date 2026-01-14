import React from 'react';
import BuildingsClient from '@/components/buildings/BuildingsClient';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Постройки',
  description: 'Просмотр постройки игроков на серверах.',
};

export default function BuildingsPage() {
  return <BuildingsClient />;
}




