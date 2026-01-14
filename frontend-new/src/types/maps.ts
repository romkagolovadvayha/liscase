/**
 * Типы для системы карт (MapsV2)
 */

export interface Map {
  id: number;
  name: string;
  description?: string;
  image?: string;
  server_tag: string;
  votes_count: number;
  created_at: string;
  updated_at: string;
  has_voted?: boolean;
}







