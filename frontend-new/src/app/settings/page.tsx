import { Metadata } from 'next';
import SettingsClient from '@/components/settings/SettingsClient';

export const metadata: Metadata = {
  title: 'Настройки - Liscase',
  description: 'Настройки профиля пользователя',
};

export default async function SettingsPage() {
  return <SettingsClient />;
}






