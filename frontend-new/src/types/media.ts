/**
 * Типы для медиа галереи (Media)
 */

export interface Media {
  id: number;
  user_id: number;
  title?: string;
  description?: string;
  file: string;
  file_type: 'image' | 'video';
  likes_count: number;
  comments_count: number;
  views_count: number;
  created_at: string;
  updated_at: string;
  user?: {
    id: number;
    username: string;
    avatar?: string;
  };
  is_liked?: boolean;
  comments?: MediaComment[];
}

export interface MediaComment {
  id: number;
  media_id: number;
  user_id: number;
  comment: string;
  created_at: string;
  user?: {
    id: number;
    username: string;
    avatar?: string;
  };
}







