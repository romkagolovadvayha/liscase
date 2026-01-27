'use client';

import React from 'react';
import Footer from './Footer';
import { useSettings } from '@/hooks/useSettings';
import { getLogo } from '@/lib/utils/settingsImage';

export default function FooterClient() {
  const { data: settings, isLoading } = useSettings();

  // Используем дефолтные значения во время загрузки или при ошибке
  const defaultSettings = {
    site_email: 'support@example.com',
    site_domain: 'prostoj.store',
    personal_info_ip_inn: '180600035048',
    personal_info_ip_fio: 'ИП УСКОВ АРТЕМ ОЛЕГОВИЧ',
    social_telegram_channel: '',
    social_vk: '',
    social_discord: '',
  };

  const settingsData = settings || {};

  // Получаем CDN URL из настроек
  const cdnUrl = settingsData.site?.cdnUrl as string | null | undefined;

  // Получаем логотип через утилиту (с учетом CDN)
  const logo = getLogo(settings, cdnUrl);

  // Получаем остальные настройки из API или используем дефолтные значения
  const email = (settingsData.site?.email as string) || defaultSettings.site_email;
  const domain = (settingsData.site?.domain as string) || defaultSettings.site_domain;
  
  // Информация об ИП берется из категории personal_info_ip
  // Пробуем разные варианты ключей (inn/fio или personal_info_ip_inn/personal_info_ip_fio)
  const inn = (settingsData.personal_info_ip?.inn as string) 
    || (settingsData.personal_info_ip?.personal_info_ip_inn as string)
    || (settingsData.site?.personal_info_ip_inn as string)
    || defaultSettings.personal_info_ip_inn;
  
  const ipName = (settingsData.personal_info_ip?.fio as string)
    || (settingsData.personal_info_ip?.personal_info_ip_fio as string)
    || (settingsData.site?.personal_info_ip_fio as string)
    || defaultSettings.personal_info_ip_fio;

  // Формируем массив социальных ссылок из настроек
  const socialLinks = [
    ...(settingsData.social?.telegram_channel ? [{ name: 'telegram' as const, url: settingsData.social.telegram_channel as string }] : []),
    ...(settingsData.social?.vk ? [{ name: 'vk' as const, url: settingsData.social.vk as string }] : []),
    ...(settingsData.social?.discord ? [{ name: 'discord' as const, url: settingsData.social.discord as string }] : []),
  ];

  return (
    <Footer
      logo={logo}
      email={email}
      domain={domain}
      inn={inn}
      ipName={ipName}
      socialLinks={socialLinks}
    />
  );
}

