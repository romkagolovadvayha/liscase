'use client';

import React, { useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import apiClient from '@/lib/api/client';

export default function SupportAuthCheck() {
  const router = useRouter();
  const [isChecking, setIsChecking] = useState(true);
  const [isAuthorized, setIsAuthorized] = useState(false);

  useEffect(() => {
    // Проверяем авторизацию через API
    const checkAuth = async () => {
      try {
        const response = await apiClient.get('/auth/me');
        const data = response.data;
        
        if (data.success && !data.data.isGuest) {
          // Пользователь авторизован - обновляем страницу для получения данных
          setIsAuthorized(true);
          router.refresh();
        } else {
          setIsAuthorized(false);
        }
      } catch (error) {
        console.error('Error checking auth:', error);
        setIsAuthorized(false);
      } finally {
        setIsChecking(false);
      }
    };

    checkAuth();
  }, [router]);

  if (isChecking) {
    return (
      <div className="support-page">
        <div className="support-container">
          <div className="support-auth">
            <h1>Поддержка</h1>
            <p>Проверка авторизации...</p>
          </div>
        </div>
      </div>
    );
  }

  if (isAuthorized) {
    return (
      <div className="support-page">
        <div className="support-container">
          <div className="support-auth">
            <h1>Поддержка</h1>
            <p>Загрузка данных...</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="support-page">
      <div className="support-container">
        <div className="support-auth">
          <h1>Поддержка</h1>
          <p>Для доступа к поддержке необходимо авторизоваться</p>
          <p style={{ fontSize: '14px', color: 'var(--text-secondary)', marginTop: '10px' }}>
            Если вы уже авторизованы, попробуйте обновить страницу или войти заново.
          </p>
        </div>
      </div>
    </div>
  );
}

