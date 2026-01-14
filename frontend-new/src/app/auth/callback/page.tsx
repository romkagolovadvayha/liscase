'use client';

import React, { useEffect, Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import { setTokens } from '@/lib/api/client';

/**
 * Клиентский компонент для обработки OAuth callback
 */
function AuthCallbackContent() {
  const searchParams = useSearchParams();

  useEffect(() => {
    try {
      const token = searchParams.get('token');
      const refreshToken = searchParams.get('refresh_token');

      if (token && refreshToken) {
        // Сохраняем токены
        setTokens(token, refreshToken);
        
        // Используем window.location.href для полной перезагрузки страницы
        // Это гарантирует, что Header и другие компоненты загрузятся с правильными токенами
        window.location.href = '/';
      } else {
        // Если токены не получены, редирект на главную
        window.location.href = '/';
      }
    } catch (error) {
      console.error('Ошибка при обработке callback:', error);
      // В случае ошибки также редиректим на главную
      window.location.href = '/';
    }
  }, [searchParams]);

  return (
    <div style={{ 
      display: 'flex', 
      justifyContent: 'center', 
      alignItems: 'center', 
      minHeight: '100vh',
      flexDirection: 'column',
      gap: '1rem'
    }}>
      <p>Обработка авторизации...</p>
    </div>
  );
}

/**
 * Страница обработки OAuth callback от API
 * Получает токены из URL параметров и сохраняет их
 */
export default function AuthCallbackPage() {
  return (
    <Suspense fallback={
      <div style={{ 
        display: 'flex', 
        justifyContent: 'center', 
        alignItems: 'center', 
        minHeight: '100vh',
        flexDirection: 'column',
        gap: '1rem'
      }}>
        <p>Загрузка...</p>
      </div>
    }>
      <AuthCallbackContent />
    </Suspense>
  );
}

