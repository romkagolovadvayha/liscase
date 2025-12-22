import { Metadata } from 'next';
import ProfileInfoClient from '@/components/profile/ProfileInfoClient';

export const metadata: Metadata = {
  title: 'Профиль - Liscase',
  description: 'Информация о пользователе',
};

export default async function ProfilePage() {
  return <ProfileInfoClient />;
}
