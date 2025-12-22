'use client';

import { useEffect } from 'react';

export default function ProfilePageWrapper({ children }: { children: React.ReactNode }) {
  useEffect(() => {
    // Добавляем класс к body
    const body = document.body;
    body.classList.add('profile-page-active');
    
    // Также добавляем стили напрямую для надежности
    const sidebar = document.querySelector('.main-layout__sidebar') as HTMLElement;
    const mainLayout = document.querySelector('.main-layout') as HTMLElement;
    
    if (sidebar) {
      sidebar.style.display = 'none';
    }
    if (mainLayout) {
      mainLayout.style.gridTemplateColumns = '1fr';
    }
    
    return () => {
      body.classList.remove('profile-page-active');
      if (sidebar) {
        sidebar.style.display = '';
      }
      if (mainLayout) {
        mainLayout.style.gridTemplateColumns = '';
      }
    };
  }, []);

  return <>{children}</>;
}

