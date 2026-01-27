'use client';

import React from 'react';

export interface BlogLatestPostImageProps {
  src: string;
  alt: string;
  className?: string;
}

export default function BlogLatestPostImage({ src, alt, className = '' }: BlogLatestPostImageProps) {
  return (
    <div className={`blog-latest-post__image ${className}`}>
      <img
        src={src}
        alt={alt}
        className="blog-latest-post__image-img"
        loading="lazy"
      />
    </div>
  );
}
