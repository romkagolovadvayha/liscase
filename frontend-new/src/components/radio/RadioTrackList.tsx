'use client';

import React from 'react';
import type { RadioTrack } from '@/types/radio';

interface RadioTrackListProps {
  tracks: RadioTrack[];
  currentTrack: RadioTrack | null | undefined;
  onTrackSelect: (track: RadioTrack) => void;
  onLike: (trackId: number) => void;
  isLoading: boolean;
}

export default function RadioTrackList({
  tracks,
  currentTrack,
  onTrackSelect,
  onLike,
  isLoading,
}: RadioTrackListProps) {
  if (isLoading) {
    return <div className="radio-track-list-loading">Загрузка...</div>;
  }

  if (tracks.length === 0) {
    return <div className="radio-track-list-empty">Нет треков</div>;
  }

  return (
    <div className="radio-track-list">
      <h2 className="radio-track-list-title">Треки</h2>
      <div className="radio-track-list-items">
        {tracks.map((track) => (
          <div
            key={track.id}
            className={`radio-track-item ${
              currentTrack?.id === track.id ? 'active' : ''
            }`}
            onClick={() => onTrackSelect(track)}
          >
            <div className="radio-track-item-info">
              <h4 className="radio-track-item-title">
                {track.artist ? `${track.artist} - ` : ''}
                {track.title}
              </h4>
              {track.duration && (
                <span className="radio-track-item-duration">
                  {Math.floor(track.duration / 60)}:
                  {Math.floor(track.duration % 60)
                    .toString()
                    .padStart(2, '0')}
                </span>
              )}
            </div>
            <div className="radio-track-item-actions">
              <button
                className={`radio-track-item-like ${track.is_liked ? 'liked' : ''}`}
                onClick={(e) => {
                  e.stopPropagation();
                  onLike(track.id);
                }}
              >
                ❤️ {track.likes_count}
              </button>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}







