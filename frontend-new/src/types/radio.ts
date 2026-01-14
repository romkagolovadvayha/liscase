/**
 * Типы для системы радио (Radio)
 */

export interface RadioStation {
  id: number;
  name: string;
  description?: string;
  stream_url: string;
  is_active: boolean;
  current_track_id?: number;
  listeners_count: number;
  created_at: string;
  updated_at: string;
  currentTrack?: RadioTrack;
}

export interface RadioTrack {
  id: number;
  station_id: number;
  title: string;
  artist?: string;
  file: string; // Путь к файлу
  duration?: number;
  likes_count: number;
  plays_count: number;
  sort: number;
  is_active: boolean;
  created_at: string;
  updated_at: string;
  is_liked?: boolean;
}

export interface RadioQueue {
  track_id: number;
  station_id: number;
  track?: RadioTrack;
}







