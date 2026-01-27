'use client';

import React, { useState, useEffect } from 'react';
import type { Media } from '@/types/media';
import MediaGrid from './MediaGrid';
import MediaModal from './MediaModal';
import apiClient from '@/lib/api/client';

interface MediaClientProps {
  initialData?: {
    media: Media[];
  };
}

export default function MediaClient({ initialData }: MediaClientProps) {
  const [media, setMedia] = useState<Media[]>(initialData?.media || []);
  const [selectedMedia, setSelectedMedia] = useState<Media | null>(null);
  const [isLoading, setIsLoading] = useState(!initialData);

  useEffect(() => {
    if (!initialData) {
      setIsLoading(true);
      apiClient.get('/media')
        .then(response => {
          if (response.data.success) {
            setMedia(response.data.data?.media || []);
          }
        })
        .catch(error => {
          console.error('Failed to fetch media:', error);
        })
        .finally(() => {
          setIsLoading(false);
        });
    }
  }, [initialData]);

  const handleLike = async (mediaId: number) => {
    try {
      const response = await fetch(`/api/media/${mediaId}/like`, {
        method: 'POST',
      });

      if (!response.ok) {
        throw new Error('Failed to toggle like');
      }

      const result = await response.json();

      // Обновляем состояние
      setMedia((prev) =>
        prev.map((item) =>
          item.id === mediaId
            ? {
                ...item,
                is_liked: result.liked,
                likes_count: result.liked ? item.likes_count + 1 : Math.max(item.likes_count - 1, 0),
              }
            : item
        )
      );

      if (selectedMedia && selectedMedia.id === mediaId) {
        setSelectedMedia({
          ...selectedMedia,
          is_liked: result.liked,
          likes_count: result.liked ? selectedMedia.likes_count + 1 : Math.max(selectedMedia.likes_count - 1, 0),
        });
      }
    } catch (error) {
      console.error('Error toggling like:', error);
    }
  };

  if (isLoading) {
    return (
      <div className="media-page">
        <div className="media-container">
          <div className="media-header">
            <h1>Медиа галерея</h1>
          </div>
          <div className="media-content">
            <div className="media-empty">Загрузка...</div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="media-page">
      <div className="media-container">
        <div className="media-header">
          <h1>Медиа галерея</h1>
        </div>
        {media.length === 0 ? (
          <div className="media-content">
            <div className="media-empty">Нет медиа</div>
          </div>
        ) : (
          <>
            <MediaGrid media={media} onSelect={setSelectedMedia} />
            {selectedMedia && (
              <MediaModal
                media={selectedMedia}
                onClose={() => setSelectedMedia(null)}
                onLike={handleLike}
              />
            )}
          </>
        )}
      </div>
    </div>
  );
}







