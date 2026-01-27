'use client';

import React from 'react';
import Image from 'next/image';
import type { Media } from '@/types/media';

interface MediaGridProps {
  media: Media[];
  onSelect: (media: Media) => void;
}

export default function MediaGrid({ media, onSelect }: MediaGridProps) {
  return (
    <div className="media-grid">
      {media.map((item) => (
        <div
          key={item.id}
          className="media-item"
          onClick={() => onSelect(item)}
        >
          {item.file_type === 'image' ? (
            <Image
              src={`/uploads/media/${item.file}`}
              alt={item.title || 'Media'}
              width={300}
              height={300}
              className="media-item-image"
            />
          ) : (
            <video
              src={`/uploads/media/${item.file}`}
              className="media-item-video"
              muted
            />
          )}
          <div className="media-item-overlay">
            <div className="media-item-stats">
              <span>❤️ {item.likes_count}</span>
              <span>💬 {item.comments_count}</span>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}







