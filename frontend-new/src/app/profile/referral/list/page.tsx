import { Metadata } from 'next';
import { Suspense } from 'react';
import ProfileReferralClient from '@/components/profile/ProfileReferralClient';

export const metadata: Metadata = {
  title: 'Мои рефералы - Реферальная система - Liscase',
  description: 'Список ваших приглашенных пользователей',
};

export default async function ProfileReferralListPage() {
  return (
    <Suspense fallback={<div>Загрузка...</div>}>
      <ProfileReferralClient />
    </Suspense>
  );
}






