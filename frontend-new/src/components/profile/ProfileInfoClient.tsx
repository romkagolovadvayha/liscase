'use client';

import React, { useState, useEffect } from 'react';
import { Avatar } from 'antd';
import ProfileTabs from '@/components/profile/ProfileTabs';
import Icon from '@/components/icons/Icon';
import Link from 'next/link';
import '@/styles/profile.scss';

interface UserData {
  id: number;
  username: string;
  steamId: string;
  balance: number;
  avatar: string;
  activeVip?: {
    expires_at: string;
    timestamp: number;
  } | null;
}

export default function ProfileInfoClient() {
  const [loading, setLoading] = useState(true);
  const [userData, setUserData] = useState<UserData | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    fetchUserData();
  }, []);

  const fetchUserData = async () => {
    try {
      setLoading(true);
      const response = await fetch('/api/auth/me');
      const data = await response.json();

      if (data.success && data.user) {
        // Получаем VIP статус
        const vipResponse = await fetch('/api/profile');
        const vipData = await vipResponse.json();
        
        setUserData({
          ...data.user,
          activeVip: vipData.success && vipData.profile?.hasVip ? {
            expires_at: '',
            timestamp: 0,
          } : null,
        });
      } else {
        setError('Не удалось загрузить данные пользователя');
      }
    } catch (err: any) {
      setError(err.message || 'Ошибка при загрузке данных');
    } finally {
      setLoading(false);
    }
  };

  if (loading) {
    return (
      <div>
        <div className="loading">Загрузка...</div>
      </div>
    );
  }

  if (error || !userData) {
    return (
      <div>
        <div className="error">{error || 'Не удалось загрузить данные пользователя'}</div>
      </div>
    );
  }

  const tabs = [
    { id: 'info', label: 'Информация о пользователе', icon: 'info', href: '/profile' },
    { id: 'history', label: 'История операций', icon: 'article', href: '/profile/history' },
    { id: 'referral', label: 'Реферальная система', icon: 'people', href: '/profile/referral' },
    { id: 'settings', label: 'Настройки', icon: 'palette', href: '/profile/settings' },
  ];

  return (
    <div>
      <ProfileTabs tabs={tabs} />

      <section className="profile-section">
        <div className="profile-info">
          <div className="profile-info__avatar">
            <Avatar src={userData.avatar} alt={userData.username} size={120} />
          </div>
          <div className="profile-info__details">
            <h2 className="profile-info__username">{userData.username}</h2>
            <div className="profile-info__steam">
              <a
                href={`https://steamcommunity.com/profiles/${userData.steamId}`}
                target="_blank"
                rel="noopener noreferrer"
                className="profile-info__steam-link"
              >
                <Icon name="steam" faSize="sm" />
                <span>Steam ID: {userData.steamId}</span>
              </a>
            </div>
            <div className="profile-info__balance">
              <span className="profile-info__balance-label">Баланс:</span>
              <span className="profile-info__balance-value">
                {userData.balance.toLocaleString('ru-RU')}
              </span>
            </div>
            {userData.activeVip && (
              <div className="profile-info__vip">
                <Icon name="crown" fontSize="small" />
                <span>VIP статус активен</span>
              </div>
            )}
          </div>
        </div>
      </section>
    </div>
  );
}

