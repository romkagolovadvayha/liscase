/**
 * TypeScript типы данных на основе моделей из common/models
 * Автоматически сгенерировано на основе PHP моделей
 */

// ==================== User Types ====================
export interface User {
  id: number;
  user_id: number;
  email: string;
  steam_id: string;
  telegram_chat_id: number;
  vk_id: number | null;
  discord_id: string | null;
  username: string;
  password_hash: string;
  auth_key: string;
  ref_code: number;
  socket_room: string;
  current_language: string;
  status: number;
  jwt: string;
  parent_skin_send: boolean;
  auto: number;
  server_tag: string;
  created_at: string;
  updated_at: string;
  last_visit_server_at: string;
  banned_at: string;
  unbanned_at: string;
  ban_reason: number;
  ban_by: number;
  discord: string;
  rustru_activated: boolean;
  rustru_scrap_confirm: number;
  rustru_scrap_wait: number;
  is_gamer: number;
  server_id: number;
  raid_notify: boolean;
  ban_notify: boolean;
  store: boolean;
  is_stats: boolean;
  blocked_support_at: string;
  blocked_support: boolean;
  stat_status: string;
  avatar_frame: number;
  is_email: boolean;
  ip: string;
  promocode: string;
  ping: number;
  is_mirror_registration: boolean;
  is_mirror_returned: boolean;
  is_telegram_blocked: boolean;
  floating_price_percent: number;
}

export interface UserProfile {
  id: number;
  user_id: number;
  // Добавить поля из UserProfile модели
}

export interface UserBalance {
  id: number;
  user_id: number;
  balance: number;
  currency: string;
  // Добавить остальные поля
}

// ==================== Server Types ====================
export interface Server {
  id: number;
  name: string;
  wipe: string;
  wipe_type: number;
  next_wipe: string;
  global_wipe: string;
  description: string;
  rules: string;
  ip: string;
  text_ip: string;
  port: number;
  query: number;
  rcon: number;
  rcon_password: string;
  map: string;
  players: number;
  joined: number;
  queued: number;
  max: number;
  team_limit: number;
  status: ServerStatus;
  db_host: string;
  db_name: string;
  db_user: string;
  db_password: string;
  tag: string;
  stats_payment: boolean;
  skindrops: boolean;
  is_store: boolean;
  secret_map: boolean;
  wargm_id: number;
  commands: string;
  discord_token: string;
  sort: number;
  updated_at: string;
  monitoring_name: string;
  monitoring_description: string;
  rust_app_id: string;
  min_map_size: number;
  max_map_size: number;
  map_id: number;
  map_list_id: number;
  secret_key: string;
}

export enum ServerStatus {
  NOACTIVE = 0, // Выключен
  ACTIVE = 1,   // Включен
  WAIT = 2,     // Скоро откроется
  CLOSED = 3,   // Закрыт
}

// ==================== Blog Types ====================
export interface Blog {
  id: number;
  user_id: number;
  name: string;
  content: string;
  views: number;
  description: string;
  keywords: string;
  blog_category_id: number;
  link_name: string;
  status: BlogStatus;
  created_at: string;
  news_id: string;
}

export enum BlogStatus {
  NOT_ACTIVE = 0,
  ACTIVE = 1,
}

export interface BlogCategory {
  id: number;
  name: string;
  link_name: string;
  // Добавить остальные поля
}

// ==================== Drop/Product Types ====================
export interface Drop {
  id: number;
  name: string;
  eng_name: string;
  quality: string;
  market_status: number;
  min_box: number;
  max_box: number;
  description: string;
  market_id: string;
  count: number;
  discount: number;
  category_id: number;
  drop_type: DropType;
  rust_id: string;
  command: string;
  type_id: string;
  price: number;
  blocked_hour: number;
  blocked_at: string;
  status: DropStatus;
  created_at: string;
  show_main_block: boolean;
  sort: number;
  floating_price_percent: number;
  full_only: number;
  is_blocked_building: number;
}

export enum DropType {
  DROP = 0,    // Предмет
  COMMAND = 1, // Команда
  SET = 2,     // Набор предметов
  SELECT = 3,  // Выбор
  VIP = 4,     // VIP
}

export enum DropStatus {
  NOT_ACTIVE = 0,
  ACTIVE = 1,
}

export interface Category {
  id: number;
  name: string;
  // Добавить остальные поля
}

// ==================== Statistics Types ====================
export interface Statistics {
  id: number;
  user_id: number;
  server_id: number;
  // Добавить поля из Statistics модели
}

export interface Kills {
  id: number;
  user_id: number;
  target_id: number;
  server_id: number;
  // Добавить остальные поля
}

// ==================== Invoice Types ====================
export interface Invoice {
  id: number;
  user_id: number;
  amount: number;
  status: InvoiceStatus;
  created_at: string;
  // Добавить остальные поля
}

export enum InvoiceStatus {
  PENDING = 0,
  PAID = 1,
  CANCELLED = 2,
}

// ==================== User Drop Types ====================
export interface UserDrop {
  id: number;
  user_id: number;
  drop_id: number;
  count: number;
  // Добавить остальные поля
}

// ==================== Building Types ====================
export interface Building {
  id: number;
  user_id: number;
  server_id: number;
  name: string;
  // Добавить остальные поля
}

// ==================== Team Types ====================
export interface Team {
  id: number;
  name: string;
  server_id: number;
  // Добавить остальные поля
}

// ==================== Support Types ====================
export interface Support {
  id: number;
  user_id: number;
  subject: string;
  status: SupportStatus;
  created_at: string;
  // Добавить остальные поля
}

export enum SupportStatus {
  OPEN = 0,
  IN_PROGRESS = 1,
  CLOSED = 2,
}

// ==================== Media Types ====================
export interface Media {
  id: number;
  user_id: number;
  title: string;
  type: MediaType;
  url: string;
  status: MediaStatus;
  created_at: string;
  // Добавить остальные поля
}

export enum MediaType {
  IMAGE = 0,
  VIDEO = 1,
}

export enum MediaStatus {
  PENDING = 0,
  APPROVED = 1,
  REJECTED = 2,
}

// ==================== Common Types ====================
export interface PaginationParams {
  page?: number;
  perPage?: number;
  limit?: number;
  offset?: number;
}

export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  message?: string;
  errors?: Record<string, string[]>;
}

export interface PaginatedResponse<T> {
  items: T[];
  totalCount: number;
  pageCount: number;
  currentPage: number;
  perPage: number;
}










