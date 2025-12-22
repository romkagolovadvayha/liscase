import { Metadata } from 'next';
import { Suspense } from 'react';
import ProfileReferralClient from '@/components/profile/ProfileReferralClient';

export const metadata: Metadata = {
  title: 'Реферальная система - Liscase',
  description: 'Реферальная система',
};

export default async function ProfileReferralPage() {
  return (
    <Suspense fallback={<div>Загрузка...</div>}>
      <ProfileReferralClient />
    </Suspense>
  );
}






