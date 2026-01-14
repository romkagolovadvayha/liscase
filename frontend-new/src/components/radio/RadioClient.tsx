'use client';

import React, { useState, useEffect } from 'react';
import type { RadioStation, RadioTrack } from '@/types/radio';
import RadioPlayer from './RadioPlayer';
import RadioTrackList from './RadioTrackList';

interface RadioClientProps {
  initialData: {
    stations: RadioStation[];
  };
}

export default function RadioClient({ initialData }: RadioClientProps) {
  const [selectedStation, setSelectedStation] = useState<RadioStation | null>(
    initialData.stations[0] || null
  );
  const [tracks, setTracks] = useState<RadioTrack[]>([]);
  const [currentTrack, setCurrentTrack] = useState<RadioTrack | null>(null);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (selectedStation) {
      loadTracks(selectedStation.id);
    }
  }, [selectedStation]);

  const loadTracks = async (stationId: number) => {
    setIsLoading(true);
    try {
      // TODO: Endpoint для треков радио пока не реализован в API
      // Временно оставляем пустой массив
      console.warn('Radio tracks endpoint not implemented');
      setTracks([]);
    } catch (error) {
      console.error('Error loading tracks:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleTrackSelect = (track: RadioTrack) => {
    setCurrentTrack(track);
  };

  const handleLike = async (trackId: number) => {
    try {
      // TODO: Endpoint для лайков треков радио пока не реализован в API
      console.warn('Radio like endpoint not implemented');
    } catch (error) {
      console.error('Error liking track:', error);
    }
  };

  return (
    <div className="radio-page">
      <div className="radio-container">
        <div className="radio-header">
          <h1>Радиостанция</h1>
        </div>
        <div className="radio-stations">
          {initialData.stations.map((station) => (
            <button
              key={station.id}
              className={`radio-station-button ${
                selectedStation?.id === station.id ? 'active' : ''
              }`}
              onClick={() => setSelectedStation(station)}
            >
              {station.name}
            </button>
          ))}
        </div>
        {selectedStation && (
          <>
            <RadioPlayer
              station={selectedStation}
              track={currentTrack || selectedStation.currentTrack}
            />
            <RadioTrackList
              tracks={tracks}
              currentTrack={currentTrack || selectedStation.currentTrack}
              onTrackSelect={handleTrackSelect}
              onLike={handleLike}
              isLoading={isLoading}
            />
          </>
        )}
      </div>
    </div>
  );
}




