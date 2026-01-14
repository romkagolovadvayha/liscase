'use client';

import React from 'react';
import { useSettingsCSSVariables } from '@/hooks/useSettingsCSSVariables';

/**
 * Провайдер для обновления CSS переменных из настроек API
 * Должен быть внутри QueryProvider, чтобы иметь доступ к useSettings
 */
export default function SettingsCSSVariablesProvider({
  children,
}: {
  children: React.ReactNode;
}) {
  useSettingsCSSVariables();
  
  return <>{children}</>;
}

