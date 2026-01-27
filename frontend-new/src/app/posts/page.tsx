'use client';

import React, { Suspense } from 'react';
import BlogClient from '@/components/blog/BlogClient';

function BlogPageContent() {
  return <BlogClient />;
}

export default function BlogPage() {
  return (
    <Suspense fallback={<div>Загрузка...</div>}>
      <BlogPageContent />
    </Suspense>
  );
}
