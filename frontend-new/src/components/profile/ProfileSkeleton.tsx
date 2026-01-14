'use client';

import React from 'react';
import Skeleton, { SkeletonTheme } from 'react-loading-skeleton';
import 'react-loading-skeleton/dist/skeleton.css';
import '@/styles/profile.scss';

export default function ProfileSkeleton() {
  return (
    <SkeletonTheme
      baseColor="var(--background-teritiary)"
      highlightColor="var(--background-secondary)"
    >
      <div className="profile-section">
        <div className="profile-info">
          <div className="profile-info__avatar">
            <Skeleton circle height={120} width={120} />
          </div>
          <div className="profile-info__details">
            <Skeleton height={32} width="200px" style={{ marginBottom: '16px' }} />
            <Skeleton height={20} width="150px" style={{ marginBottom: '8px' }} />
            <Skeleton height={24} width="100px" style={{ marginBottom: '8px' }} />
            <Skeleton height={20} width="120px" />
          </div>
        </div>
      </div>
    </SkeletonTheme>
  );
}




