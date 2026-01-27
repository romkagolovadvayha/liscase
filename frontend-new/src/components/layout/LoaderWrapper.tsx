'use client';

import React from 'react';
import { useUser } from '@/providers/UserProvider';
import { useHomepageData } from '@/providers/HomepageDataProvider';
import FullScreenLoader from './FullScreenLoader';

export default function LoaderWrapper({ children }: { children: React.ReactNode }) {
  const { isLoading: userLoading } = useUser();
  const { isLoading: homepageLoading } = useHomepageData();

  // Показываем лоадер пока загружаются данные пользователя или homepage
  const isLoading = userLoading || homepageLoading;

  return (
    <>
      {isLoading && <FullScreenLoader />}
      <div style={{ opacity: isLoading ? 0 : 1, transition: 'opacity 0.3s ease-in-out' }}>
        {children}
      </div>
    </>
  );
}

