'use client';

import React from 'react';
import type { TaskStats } from '@/types/tasks';

interface TaskStatsDisplayProps {
  stats: TaskStats;
}

export default function TaskStatsDisplay({ stats }: TaskStatsDisplayProps) {
  return (
    <div className="task-stats">
      <div className="task-stat">
        <div className="task-stat-label">Выполнено заданий</div>
        <div className="task-stat-value">
          {stats.completedTasks} / {stats.totalTasks} ({stats.completionPercent}%)
        </div>
      </div>
      <div className="task-stat">
        <div className="task-stat-label">Получено монет</div>
        <div className="task-stat-value">{stats.totalCoins.toLocaleString('ru-RU')}</div>
      </div>
      <div className="task-stat">
        <div className="task-stat-label">Получено наград</div>
        <div className="task-stat-value">{stats.totalRewards}</div>
      </div>
    </div>
  );
}
