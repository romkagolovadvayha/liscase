export interface PlayerProfileUser {
  id: number;
  username: string;
  steam_id: string;
  avatar: string;
  status: boolean; // true = онлайн
  youtube_link?: string | null;
  twitch_link?: string | null;
  vk_link?: string | null;
  telegram_link?: string | null;
}

export interface PlayerProfileServer {
  id: number;
  tag: string;
  name: string;
  monitoring_name: string;
}

export interface PlayerProfileStats {
  // Боевая статистика
  kills: number;
  deaths: number;
  kd: number;
  scientists: number;
  wounded: number;
  tcs_destroyed: number;
  nude_kills: number;
  
  // Попадания
  hits_head: number;
  hits_neck: number;
  hits_chest: number;
  hits_lowerspine: number;
  hits_lefthand: number;
  hits_leftleg: number;
  hits_leftfoot: number;
  hits_righthand: number;
  hits_rightleg: number;
  hits_rightfoot: number;
  
  // Ресурсы
  'sulfur.ore': number;
  wood: number;
  'metal.ore': number;
  stones: number;
  
  // Другое
  playtime: number;
  crate_open: number;
  barrel: number;
  wipes: number;
  
  // Социальные метрики
  team_members: number;
  referrals_count: number;
  comments_count: number;
  buildings_count: number;
}

export interface StatItem {
  key: string;
  name: string;
  image: string;
  count: number;
  score?: number;
}

export interface WeaponItem {
  weapon: string;
  count: number;
  image: string;
  name: string;
}

export interface Award {
  id: number;
  name: string;
  image: string;
  completed: boolean;
}

export interface TeamMember {
  id: number;
  username: string;
  steam_id: string;
  avatar: string;
  is_online: boolean | null; // null = скрыт
  is_hidden: boolean;
  is_leader: boolean;
  link: string;
  date_visit: string | null;
  time_visit: number | null;
}

export interface KillItem {
  id: number;
  type: 'kill' | 'deaths' | 'suicides' | 'scientists' | 'animal';
  steam_id: string;
  dead: string;
  weapon: string | null;
  weapon_name: string | null;
  weapon_image: string | null;
  distance: number;
  name: string | null;
  link: string | null;
  dead_name: string | null;
  dead_link: string | null;
  deadLink: string | null;
  signs: string[] | null;
  wears: string | null;
  bot: boolean;
  animal: string | null;
  animal2: string | null;
  created_at: string;
}

export interface PlayerProfileData {
  user: PlayerProfileUser;
  server: PlayerProfileServer;
  stats: PlayerProfileStats;
  weapons: WeaponItem[];
  explosives: StatItem[];
  fishing: StatItem[];
  hunters: StatItem[];
  ferm: StatItem[];
  food: StatItem[];
  tea: StatItem[];
  pie: StatItem[];
  medical: StatItem[];
  levelCards: StatItem[];
  statsBlocks: StatItem[];
  awards: Award[];
  awardsStats: {
    completed: number;
    total: number;
  };
  currentWipe: string | null;
  teamMembers: TeamMember[];
  kills: KillItem[];
}
