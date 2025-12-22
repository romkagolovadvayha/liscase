'use client';

import React from 'react';
import BlogClient from '@/components/blog/BlogClient';

export default function BlogCategoryPage({
  params,
}: {
  params: Promise<{ categoryLinkName: string }>;
}) {
  return <BlogClient />;
}




