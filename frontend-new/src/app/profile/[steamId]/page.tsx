'use client';

import React from 'react';
import { useParams, usePathname } from 'next/navigation';
import PlayerProfileClient from '@/components/profile/PlayerProfileClient';
import ProfilePageWrapper from '@/components/profile/ProfilePageWrapper';
import '@/styles/profile-player.scss';

// Список статических маршрутов, которые не должны обрабатываться этим динамическим маршрутом
const STATIC_ROUTES = ['settings', 'history', 'referral', 'invite', 'list'];

export default function PlayerProfilePage() {
  const params = useParams();
  const pathname = usePathname();
  const steamId = params?.steamId as string;

  // Проверяем, что steamId не является статическим маршрутом
  // Это должно быть сделано ДО проверки pathname, чтобы избежать проблем с маршрутизацией
  if (steamId && STATIC_ROUTES.includes(steamId)) {
    return null;
  }

  // Проверяем pathname - если это статический маршрут, не обрабатываем его здесь
  if (pathname) {
    const isStaticRoute = STATIC_ROUTES.some(route => {
      // Проверяем точное совпадение или начало пути
      return pathname === `/profile/${route}` || pathname.startsWith(`/profile/${route}/`);
    });
    
    if (isStaticRoute) {
      return null;
    }
  }

  if (!steamId) {
    return null;
  }

  // Проверяем, что steamId выглядит как Steam ID (17 цифр)
  // Steam ID обычно длинный числовой идентификатор (17 цифр)
  // Если steamId не соответствует формату Steam ID, не обрабатываем его
  if (!/^\d{17}$/.test(steamId)) {
    return null;
  }

  return (
    <ProfilePageWrapper>
      <div className="player-profile-page">
        <PlayerProfileClient steamId={steamId} />
      </div>
    </ProfilePageWrapper>
  );
}
