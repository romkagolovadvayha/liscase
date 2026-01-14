'use client';

import React, { useState, useEffect, useRef } from 'react';
import { Avatar } from 'antd';
import ProfileTabs from '@/components/profile/ProfileTabs';
import Icon from '@/components/icons/Icon';
import ProfileSkeleton from '@/components/profile/ProfileSkeleton';
import Link from 'next/link';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';
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
  const isFetchingRef = useRef(false);

  useEffect(() => {
    fetchUserData();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const fetchUserData = async () => {
    // Защита от повторных запросов
    if (isFetchingRef.current) {
      return;
    }

    if (!isAuthenticated()) {
      setError('Требуется авторизация');
      setLoading(false);
      return;
    }

    try {
      isFetchingRef.current = true;
      setLoading(true);
      const response = await apiClient.get('/auth/me');
      const data = response.data;

      if (data.success && data.data) {
        // Получаем баланс и VIP статус
        try {
          const [balanceResponse, vipResponse] = await Promise.all([
            apiClient.get('/user/balance'),
            apiClient.get('/user/profile').catch(() => ({ data: { success: false } })),
          ]);
          
          const balanceData = balanceResponse.data;
          const vipData = vipResponse.data;
          
          setUserData({
            ...data.data,
            balance: balanceData.success && balanceData.data?.balance !== undefined 
              ? balanceData.data.balance 
              : 0,
            activeVip: vipData.success && vipData.data?.hasVip ? {
              expires_at: '',
              timestamp: 0,
            } : null,
          });
        } catch (err) {
          // Если не удалось загрузить баланс, используем значение по умолчанию
          setUserData({
            ...data.data,
            balance: 0,
            activeVip: null,
          });
        }
      } else {
        setError('Не удалось загрузить данные пользователя');
      }
    } catch (err: any) {
      setError(err.message || 'Ошибка при загрузке данных');
    } finally {
      setLoading(false);
      isFetchingRef.current = false;
    }
  };

  const tabs = [
    { id: 'info', label: 'Информация о пользователе', icon: 'info', href: '/profile' },
    { id: 'history', label: 'История операций', icon: 'article', href: '/profile/history' },
    { id: 'referral', label: 'Реферальная система', icon: 'people', href: '/profile/referral' },
    { id: 'settings', label: 'Настройки', icon: 'palette', href: '/profile/settings' },
  ];

  if (loading) {
    return (
      <div>
        <ProfileTabs tabs={tabs} />
        <ProfileSkeleton />
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
                {(userData.balance ?? 0).toLocaleString('ru-RU')}
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

