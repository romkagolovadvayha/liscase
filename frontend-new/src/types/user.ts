/**
 * Типы для работы с пользователями
 */

export interface User {
  id: number;
  username: string;
  email?: string;
  steam_id?: string;
  avatar?: string;
  balance?: number;
  roles?: string[];
  created_at?: string;
  updated_at?: string;
}

export interface UserProfile {
  id: number;
  user_id: number;
  name: string;
  avatar?: string;
  bio?: string;
  server_id?: number;
}











