import React from 'react';
import SupportClient from '@/components/support/SupportClient';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Поддержка',
  description: 'Система поддержки пользователей.',
};

export default function SupportPage() {
  return <SupportClient />;
}

