import React from 'react';
import ServersClient from '@/components/servers/ServersClient';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Сервера Rust для комфортной игры',
  description: 'На нашем проекте работают несколько серверов Rust с разными параметрами. Выберите сервер по пингу, размеру карты и лимиту команды.',
};

export default function ServersPage() {
  return <ServersClient />;
}

