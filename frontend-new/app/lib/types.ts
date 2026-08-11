export type Locale = "ru" | "en";
export type ViewMode = "guest" | "auth";

export interface ServerItem {
  id: number;
  tag: string;
  name: string;
  monitoring_name?: string;
  status: number;
  players: number;
  max: number;
  joined?: number;
  queued?: number;
  text_ip?: string;
  ip: string;
  port: number;
  nextWipe?: string | null;
  nextWipeTimestamp?: number | null;
  wipeType?: string;
}

export interface ProjectStats { users: number; online: number; count: number }

export interface BlogPost {
  id: number;
  title: string;
  description?: string;
  image?: string;
  image_100?: string | null;
  views?: number;
  commentsCount?: number;
  url?: string;
  createdAt?: string;
}

export interface ProductItem {
  id: number;
  name: string;
  image: string;
  price?: number;
  priceReal?: number;
  discount?: number | null;
}

export type WipeEventType = "map_wipe" | "global_wipe" | "game_update" | "custom";
export interface WipeEvent {
  id: number;
  event_type: WipeEventType;
  event_type_label: string;
  calendar_title: string;
  event_at: string;
  start: string;
  server?: { index_with_hash: string; short_name: string; tag: string } | null;
}

export interface PublicData {
  servers: ServerItem[];
  stats: ProjectStats;
  posts: BlogPost[];
  products: ProductItem[];
  wipeEvents: WipeEvent[];
}

export interface UserInfo { username: string; avatar: string; balance: number; serverTag: string }
