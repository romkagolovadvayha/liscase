'use client';

import React from 'react';
import Image from 'next/image';
import classNames from 'classnames';
import type { Task } from '@/types/tasks';

interface TaskCardProps {
  task: Task;
  onClick: (taskId: number) => void;
}

export default function TaskCard({ task, onClick }: TaskCardProps) {
  const handleClick = () => {
    // Не открываем модалку для выполненных заданий
    if (task.userStatus?.status === 'completed') {
      return;
    }
    onClick(task.id);
  };

  const statusClass = task.userStatus?.status || 'available';
  const isCompleted = statusClass === 'completed';
  const isBlocked = statusClass === 'limit_reached' || statusClass === 'unavailable' || isCompleted;

  return (
    <div
      className={classNames('category-card', {
        'show-modal-link': !isBlocked,
        'category-card--blocked': isBlocked,
        'task-card--completed': isCompleted,
      })}
      onClick={handleClick}
      aria-disabled={isBlocked}
    >
      <div className="category-card__snowflakes">
        <span className="category-card__snowflake">❄</span>
        <span className="category-card__snowflake">❅</span>
        <span className="category-card__snowflake">❆</span>
        <span className="category-card__snowflake">❄</span>
        <span className="category-card__snowflake">❅</span>
        <span className="category-card__snowflake">❆</span>
        <span className="category-card__snowflake">❄</span>
        <span className="category-card__snowflake">❅</span>
        <span className="category-card__snowflake">❆</span>
      </div>

      {/* Бейджи */}
      {task.is_vip_only && (
        <div className="task-card__badge task-card__badge--vip">VIP</div>
      )}
      {task.type === 'repeatable' && (
        <div className="task-card__badge task-card__badge--type">Многоразовое</div>
      )}
      {task.type === 'daily_reward' && (
        <div className="task-card__badge task-card__badge--type">Ежедневное</div>
      )}

      {/* Изображение */}
      {task.image && (
        <div className="category-card__image-wrapper">
          <img className="category-card__image" src={task.image} alt={task.title} loading="lazy" />
          {/* Прогресс поверх изображения */}
          {task.progress !== null && task.maxProgress !== null && task.maxProgress !== undefined && task.maxProgress > 0 && (
            <div className="task-card__progress-overlay">
              <div className="task-card__progress-bar-overlay">
                <div
                  className="task-card__progress-fill-overlay"
                  style={{ width: `${Math.min(((task.progress ?? 0) / (task.maxProgress ?? 1)) * 100, 100)}%` }}
                />
              </div>
              <span className="task-card__progress-text-overlay">
                {task.progress} / {task.maxProgress}
              </span>
            </div>
          )}
        </div>
      )}

      {/* Заголовок */}
      <p className="category-card__title">{task.title}</p>

      {/* Награда (вместо цены) */}
      <div className="category-card__price">
        {task.type === 'daily_reward' && task.check_type === 'daily_reward' && task.userStatus?.currentReward ? (
          // Для ежедневных заданий используем currentReward из userStatus
          <>
            {task.userStatus.currentReward.currency || (task.userStatus.currentReward.reward?.drop_id === 843) ? (
              <span className="category-card__price-current">
                +{(task.userStatus.currentReward.amount || task.userStatus.currentReward.reward?.amount || 0).toLocaleString('ru-RU')} <span className="icons icons_16px icons_16px_coin"></span>
              </span>
            ) : task.userStatus.currentReward.drop || task.userStatus.currentReward.reward ? (
              <span className="category-card__price-current task-card__reward-item">
                {task.userStatus.currentReward.image && (
                  <img
                    src={task.userStatus.currentReward.image}
                    alt={task.userStatus.currentReward.name || ''}
                    className="task-card__reward-icon"
                  />
                )}
                <span>{task.userStatus.currentReward.name || ''}</span>
              </span>
            ) : (
              <span className="category-card__price-buy">Награда не указана</span>
            )}
          </>
        ) : task.reward_type === 'currency' && task.reward_amount ? (
          <span className="category-card__price-current">
            +{task.reward_amount.toLocaleString('ru-RU')} <span className="icons icons_16px icons_16px_coin"></span>
          </span>
        ) : task.reward_type === 'item' && task.reward_item ? (
          <span className="category-card__price-current task-card__reward-item">
            {task.reward_item.image && (
              <img
                src={task.reward_item.image}
                alt={task.reward_item.name}
                className="task-card__reward-icon"
              />
            )}
            <span>{task.reward_item.name}</span>
          </span>
        ) : (
          <span className="category-card__price-buy">Награда не указана</span>
        )}
      </div>

      {/* Статус (блокировка, если недоступно, но не для выполненных) */}
      {isBlocked && !isCompleted && (
        <div className="category-card__blocked">
          {statusClass === 'limit_reached' ? 'Лимит достигнут' : task.userStatus?.message || 'Недоступно'}
        </div>
      )}
      {isCompleted && (
        <div className="task-card__completed-badge">Выполнено</div>
      )}
    </div>
  );
}
