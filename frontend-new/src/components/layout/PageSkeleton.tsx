'use client';

import React from 'react';
import Skeleton, { SkeletonTheme } from 'react-loading-skeleton';
import 'react-loading-skeleton/dist/skeleton.css';
import '@/styles/page-skeleton.scss';

export default function PageSkeleton() {
  return (
    <SkeletonTheme
      baseColor="var(--background-teritiary)"
      highlightColor="var(--background-secondary)"
    >
      <div className="page-skeleton">
        <div className="page-skeleton__content">
          <Skeleton height={48} width="60%" className="page-skeleton__title" />
          <Skeleton height={20} width="80%" className="page-skeleton__text" />
          <Skeleton height={20} width="70%" className="page-skeleton__text" />
          
          <div className="page-skeleton__grid">
            {[1, 2, 3, 4, 5, 6].map((item) => (
              <div key={item} className="page-skeleton__card">
                <Skeleton height={200} className="page-skeleton__card-image" />
                <Skeleton height={24} width="80%" className="page-skeleton__card-title" />
                <Skeleton height={16} width="60%" />
              </div>
            ))}
          </div>
        </div>
      </div>
    </SkeletonTheme>
  );
}
