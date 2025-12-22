import { Metadata } from 'next';
import { Suspense } from 'react';
import ProfileReferralClient from '@/components/profile/ProfileReferralClient';

export const metadata: Metadata = {
  title: 'Как приглашать? - Реферальная система - Liscase',
  description: 'Как приглашать друзей в реферальную систему',
};

export default async function ProfileReferralInvitePage() {
  return (
    <Suspense fallback={<div>Загрузка...</div>}>
      <ProfileReferralClient />
    </Suspense>
  );
}






