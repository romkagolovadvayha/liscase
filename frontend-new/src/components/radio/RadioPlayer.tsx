'use client';

import React, { useRef, useEffect, useState } from 'react';
import type { RadioStation, RadioTrack } from '@/types/radio';

interface RadioPlayerProps {
  station: RadioStation;
  track: RadioTrack | null | undefined;
}

export default function RadioPlayer({ station, track }: RadioPlayerProps) {
  const audioRef = useRef<HTMLAudioElement>(null);
  const [isPlaying, setIsPlaying] = useState(false);
  const [currentTime, setCurrentTime] = useState(0);
  const [duration, setDuration] = useState(0);

  useEffect(() => {
    const audio = audioRef.current;
    if (!audio) return;

    const updateTime = () => setCurrentTime(audio.currentTime);
    const updateDuration = () => setDuration(audio.duration);

    audio.addEventListener('timeupdate', updateTime);
    audio.addEventListener('loadedmetadata', updateDuration);

    return () => {
      audio.removeEventListener('timeupdate', updateTime);
      audio.removeEventListener('loadedmetadata', updateDuration);
    };
  }, []);

  const togglePlay = () => {
    const audio = audioRef.current;
    if (!audio) return;

    if (isPlaying) {
      audio.pause();
    } else {
      audio.play();
    }
    setIsPlaying(!isPlaying);
  };

  const handleSeek = (e: React.ChangeEvent<HTMLInputElement>) => {
    const audio = audioRef.current;
    if (!audio) return;

    const newTime = parseFloat(e.target.value);
    audio.currentTime = newTime;
    setCurrentTime(newTime);
  };

  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = Math.floor(seconds % 60);
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  return (
    <div className="radio-player">
      <audio
        ref={audioRef}
        src={track ? `/uploads/radio/${track.file}` : station.stream_url}
        onPlay={() => setIsPlaying(true)}
        onPause={() => setIsPlaying(false)}
        onEnded={() => setIsPlaying(false)}
      />
      <div className="radio-player-info">
        <h3 className="radio-player-title">
          {track ? `${track.artist || ''} - ${track.title}` : station.name}
        </h3>
        {track && (
          <p className="radio-player-description">
            {station.name} • {track.likes_count} лайков
          </p>
        )}
      </div>
      <div className="radio-player-controls">
        <button
          className="radio-player-play-button"
          onClick={togglePlay}
        >
          {isPlaying ? '⏸' : '▶'}
        </button>
        <div className="radio-player-progress">
          <input
            type="range"
            min="0"
            max={duration || 0}
            value={currentTime}
            onChange={handleSeek}
            className="radio-player-slider"
          />
          <div className="radio-player-time">
            <span>{formatTime(currentTime)}</span>
            <span>{formatTime(duration)}</span>
          </div>
        </div>
      </div>
    </div>
  );
}







