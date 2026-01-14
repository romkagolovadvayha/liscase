/**
 * Типы для системы построек (Buildings)
 */

export interface Building {
  id: number;
  user_id: number;
  server_tag: string;
  title: string;
  description?: string;
  image?: string;
  coordinates?: string;
  likes_count: number;
  views_count: number;
  created_at: string;
  updated_at: string;
  user?: {
    id: number;
    username: string;
    avatar?: string;
  };
  is_liked?: boolean;
}







