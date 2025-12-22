'use client';

import React, { useRef, useEffect, useState } from 'react';
import { usePathname } from 'next/navigation';
import classNames from 'classnames';
import Icon from '@/components/icons/Icon';
import Tabs from '@/components/design-system/Tabs';

interface ProfileTab {
  id: string;
  label: string;
  icon: string;
  href: string;
}

interface ProfileTabsProps {
  tabs: ProfileTab[];
  className?: string;
}

export default function ProfileTabs({ tabs, className }: ProfileTabsProps) {
  const pathname = usePathname();
  
  // Определяем активную вкладку на основе pathname
  // Проверяем точное совпадение или начало пути (для вложенных страниц)
  const activeTab = tabs.find(tab => {
    if (pathname === tab.href) return true;
    // Для вложенных страниц (например, /profile/settings)
    if (tab.href !== '/profile' && pathname.startsWith(tab.href)) return true;
    return false;
  })?.id || tabs[0]?.id;

  const handleTabChange = (tabId: string) => {
    const tab = tabs.find(t => t.id === tabId);
    if (tab) {
      window.location.href = tab.href;
    }
  };

  return (
    <Tabs 
      tabs={tabs.map(tab => ({ id: tab.id, label: tab.label, icon: tab.icon }))} 
      activeTab={activeTab} 
      onChange={handleTabChange}
      className={className}
    />
  );
}

