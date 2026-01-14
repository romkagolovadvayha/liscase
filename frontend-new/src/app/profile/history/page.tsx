import React, { Suspense } from 'react';
import ProfileHistoryClient from '@/components/profile/ProfileHistoryClient';

// Страница использует useSearchParams, поэтому должна быть динамической
export const dynamic = 'force-dynamic';

export default function ProfileHistoryPage() {
  return (
    <Suspense fallback={<div>Загрузка...</div>}>
      <ProfileHistoryClient />
    </Suspense>
  );
}






