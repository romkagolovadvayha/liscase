'use client';

import React from 'react';
import BlogClient from '@/components/blog/BlogClient';

export default function BlogSubcategoryPage({
  params,
}: {
  params: Promise<{ categoryLinkName: string; subcategoryLinkName: string }>;
}) {
  return <BlogClient />;
}












