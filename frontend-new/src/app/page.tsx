import React from 'react';
import HomePageClient from '@/components/homepage/HomePageClient';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Главная',
  description: 'Главная страница проекта',
};

export default function HomePage() {
  return <HomePageClient />;
}
