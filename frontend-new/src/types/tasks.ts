/**
 * Типы для системы заданий (TasksV2)
 */

export type TaskType = 'one_time' | 'repeatable' | 'daily_reward';
export type TaskCheckType = 'manual' | 'statistics_param' | 'daily_reward' | 'vk_subscribe_group' | 'custom';
export type TaskRewardType = 'currency' | 'item';
export type TaskStatus = 'available' | 'completed' | 'limit_reached' | 'unavailable';

export interface TaskUserStatus {
  status: TaskStatus;
  message: string;
  currentReward?: any;
}

export interface Task {
  id: number;
  title: string;
  short_description?: string;
  type: TaskType;
  check_type: TaskCheckType;
  reward_type: TaskRewardType;
  reward_amount?: number | null;
  reward_item?: {
    id: number;
    name: string;
    image?: string | null;
  } | null;
  is_vip_only: boolean;
  image?: string | null;
  global_completed: number;
  sort: number;
  created_at: string;
  userStatus: TaskUserStatus;
  progress?: number | null;
  maxProgress?: number | null;
}

export interface TaskDetail extends Task {
  full_description?: string | null;
  button_text?: string | null;
  extra_buttons?: any;
  max_progress?: number | null;
}

export interface TaskStats {
  totalTasks: number;
  completedTasks: number;
  completionPercent: number;
  totalCoins: number;
  totalRewards: number;
  totalPotentialCoins: number;
  totalPotentialRewards: number;
}

export interface TaskDetailResponse {
  task: TaskDetail;
  userStatus: TaskUserStatus;
  progress?: number | null;
  maxProgress?: number | null;
  dailyRewardList?: any;
  vkCode?: string | null;
  vkGroupId?: string | null;
}
