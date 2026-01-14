import React from 'react';
import StoreClient from '@/components/store/StoreClient';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Моя корзина',
  description: 'Ваши приобретенные предметы и их статус.',
};

export default function StorePage() {
  return <StoreClient />;
}




