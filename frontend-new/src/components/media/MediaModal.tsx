'use client';

import React from 'react';
import Image from 'next/image';
import type { Media } from '@/types/media';

interface MediaModalProps {
  media: Media;
  onClose: () => void;
  onLike: (mediaId: number) => void;
}

export default function MediaModal({ media, onClose, onLike }: MediaModalProps) {
  return (
    <div className="media-modal" onClick={onClose}>
      <div className="media-modal-content" onClick={(e) => e.stopPropagation()}>
        <button className="media-modal-close" onClick={onClose}>
          ×
        </button>
        {media.file_type === 'image' ? (
          <Image
            src={`/uploads/media/${media.file}`}
            alt={media.title || 'Media'}
            width={800}
            height={600}
            className="media-modal-image"
          />
        ) : (
          <video
            src={`/uploads/media/${media.file}`}
            className="media-modal-video"
            controls
            autoPlay
          />
        )}
        <div className="media-modal-info">
          <div className="media-modal-header">
            <h2>{media.title || 'Без названия'}</h2>
            <button
              className={`media-modal-like ${media.is_liked ? 'active' : ''}`}
              onClick={() => onLike(media.id)}
            >
              ❤️ {media.likes_count}
            </button>
          </div>
          {media.description && (
            <p className="media-modal-description">{media.description}</p>
          )}
        </div>
      </div>
    </div>
  );
}







