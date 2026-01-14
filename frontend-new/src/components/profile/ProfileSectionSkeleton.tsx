'use client';

import React from 'react';
import Skeleton, { SkeletonTheme } from 'react-loading-skeleton';
import 'react-loading-skeleton/dist/skeleton.css';
import '@/styles/profile.scss';

export default function ProfileSectionSkeleton() {
  return (
    <SkeletonTheme
      baseColor="var(--background-teritiary)"
      highlightColor="var(--background-secondary)"
    >
      <div className="profile-section">
        <Skeleton height={32} width="200px" style={{ marginBottom: '24px' }} />
        <Skeleton height={20} width="80%" style={{ marginBottom: '16px' }} />
        <Skeleton height={20} width="70%" style={{ marginBottom: '24px' }} />
        <Skeleton height={200} />
      </div>
    </SkeletonTheme>
  );
}




