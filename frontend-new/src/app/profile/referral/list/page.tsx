import React, { Suspense } from 'react';
import ProfileReferralClient from '@/components/profile/ProfileReferralClient';

// Страница использует useSearchParams, поэтому должна быть динамической
export const dynamic = 'force-dynamic';

export default function ProfileReferralListPage() {
  return (
    <Suspense fallback={<div>Загрузка...</div>}>
      <ProfileReferralClient />
    </Suspense>
  );
}
