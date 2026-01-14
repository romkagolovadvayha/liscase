'use client';

import React, { useState, useEffect } from 'react';
import { toastSuccess, toastError } from '@/lib/toast';
import Icon from '@/components/icons/Icon';
import Button from '@/components/forms/Button';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';
import type { TaskDetail, TaskDetailResponse, TaskUserStatus } from '@/types/tasks';
import { useSettings } from '@/hooks/useSettings';
import { getModalLightImage } from '@/lib/utils/settingsImage';
import '@/styles/product-modal.scss';

interface TaskModalProps {
  taskId: number | null;
  isOpen: boolean;
  onClose: () => void;
  onTaskCompleted?: () => void;
}

export default function TaskModal({
  taskId,
  isOpen,
  onClose,
  onTaskCompleted,
}: TaskModalProps) {
  const { data: settings } = useSettings();
  const modalLightImage = getModalLightImage(settings);
  const [task, setTask] = useState<TaskDetail | null>(null);
  const [userStatus, setUserStatus] = useState<TaskUserStatus | null>(null);
  const [loading, setLoading] = useState(false);
  const [checking, setChecking] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [progress, setProgress] = useState<number | null>(null);
  const [maxProgress, setMaxProgress] = useState<number | null>(null);

  useEffect(() => {
    if (isOpen && taskId) {
      loadTaskDetail();
    } else {
      setTask(null);
      setUserStatus(null);
      setError(null);
      setProgress(null);
      setMaxProgress(null);
    }
  }, [isOpen, taskId]);

  const loadTaskDetail = async () => {
    if (!taskId) return;

    setLoading(true);
    setError(null);
    try {
      const response = await apiClient.get(`/tasks/${taskId}`);
      const result = response.data;

      if (result.success && result.data) {
        const data: TaskDetailResponse = result.data;
        setTask(data.task);
        setUserStatus(data.userStatus);
        setProgress(data.progress ?? null);
        setMaxProgress(data.maxProgress ?? null);
      } else {
        setError(result.error?.message || 'Не удалось загрузить задание');
      }
    } catch (err: any) {
      console.error('Error loading task detail:', err);
      setError(err.response?.data?.error?.message || 'Ошибка при загрузке задания');
    } finally {
      setLoading(false);
    }
  };

  const handleCheck = async () => {
    if (!taskId || checking) return;

    setChecking(true);
    setError(null);
    try {
      const response = await apiClient.post(`/tasks/${taskId}/check`);
      const result = response.data;

      if (result.success && result.data) {
        if (result.data.success) {
          toastSuccess(result.data.message || 'Задание выполнено! Награда выдана.');
          
          // Обновляем прогресс
          if (result.data.progress !== undefined) {
            setProgress(result.data.progress);
          }
          if (result.data.maxProgress !== undefined) {
            setMaxProgress(result.data.maxProgress);
          }

          // Перезагружаем данные задания
          await loadTaskDetail();
          
          // Вызываем callback для обновления списка
          if (onTaskCompleted) {
            onTaskCompleted();
          }
        } else {
          // Задание еще не выполнено, но есть прогресс
          if (result.data.progress !== undefined) {
            setProgress(result.data.progress);
          }
          if (result.data.maxProgress !== undefined) {
            setMaxProgress(result.data.maxProgress);
          }

          // Если есть редирект, перенаправляем
          if (result.data.redirect) {
            window.open(result.data.redirect, '_blank');
          }

          // Показываем сообщение
          if (result.data.message) {
            toastError(result.data.message);
            setError(result.data.message);
          }
        }
      } else {
        setError(result.error?.message || 'Ошибка при выполнении задания');
      }
    } catch (err: any) {
      console.error('Error checking task:', err);
      const errorMessage = err.response?.data?.error?.message || 'Ошибка при выполнении задания';
      setError(errorMessage);
      toastError(errorMessage);
    } finally {
      setChecking(false);
    }
  };

  const handleOverlayClick = (e: React.MouseEvent<HTMLDivElement>) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  };

  if (!isOpen) return null;

  const canCheck = userStatus?.status === 'available';
  const isCompleted = userStatus?.status === 'completed';
  const isLimitReached = userStatus?.status === 'limit_reached';

  return (
    <div className="product-modal-overlay" onClick={handleOverlayClick}>
      <div className="product-modal">
        {/* Снежинки для эффекта объема */}
        <span className="product-modal__snowflake product-modal__snowflake--1">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--2">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--3">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--4">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--5">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--6">❄</span>
        <button className="product-modal__close" onClick={onClose}>
          <Icon name="close" fontSize="small" />
        </button>

        {loading ? (
          <div className="product-modal__loading">
            <Icon name="loading" fontSize="large" />
            <span>Загрузка...</span>
          </div>
        ) : error && !task ? (
          <div className="product-modal__error">
            <p>{error}</p>
            <Button onClick={onClose} variant="secondary">
              Закрыть
            </Button>
          </div>
        ) : task ? (
          <>
            <header className="product-modal__header">
              <h2 className="product-modal__title">{task.title}</h2>
              {task.is_vip_only && (
                <span className="task-modal__badge task-modal__badge--vip">VIP</span>
              )}
            </header>
            <div className="product-modal__content">
              {task.image && (
                <figure className="product-modal__image-wrapper">
                  <img
                    src={modalLightImage}
                    alt=""
                    className="product-modal__light"
                  />
                  <img
                    src={task.image}
                    alt={task.title}
                    className="product-modal__image"
                  />
                </figure>
              )}

              <div className="product-modal__info">
                {/* Описание */}
                {task.short_description && (
                  <p className="product-modal__description">{task.short_description}</p>
                )}
                {task.full_description && (
                  <div className="task-modal__full-description" dangerouslySetInnerHTML={{ __html: task.full_description }} />
                )}

                {/* Прогресс */}
                {progress !== null && maxProgress !== null && maxProgress > 0 && (
                  <div className="task-modal__progress-block">
                    <div className="task-modal__progress-header">
                      <span className="task-modal__progress-label">Прогресс выполнения</span>
                      <span className="task-modal__progress-value">{progress} / {maxProgress}</span>
                    </div>
                    <div className="task-modal__progress-bar">
                      <div
                        className="task-modal__progress-fill"
                        style={{ width: `${Math.min((progress / maxProgress) * 100, 100)}%` }}
                      />
                    </div>
                  </div>
                )}

                {/* Награда */}
                <div className="task-modal__reward-block">
                  <h3 className="task-modal__reward-title">Награда за выполнение</h3>
                  {task.type === 'daily_reward' && task.check_type === 'daily_reward' && userStatus?.currentReward ? (
                    // Для ежедневных заданий используем currentReward из userStatus
                    <>
                      {userStatus.currentReward.currency || (userStatus.currentReward.reward?.drop_id === 843) ? (
                        <div className="task-modal__reward-currency">
                          <span className="task-modal__reward-amount">
                            {(userStatus.currentReward.amount || userStatus.currentReward.reward?.amount || 0).toLocaleString('ru-RU')}
                          </span>
                          <span className="task-modal__reward-icon task-modal__reward-icon--coin">
                            <span className="icons icons_16px icons_16px_coin"></span>
                          </span>
                        </div>
                      ) : userStatus.currentReward.drop || userStatus.currentReward.reward ? (
                        <div className="task-modal__reward-item">
                          {userStatus.currentReward.image && (
                            <div className="task-modal__reward-image-wrapper">
                              <img
                                src={userStatus.currentReward.image}
                                alt={userStatus.currentReward.name || ''}
                                className="task-modal__reward-image"
                              />
                            </div>
                          )}
                          <div className="task-modal__reward-info">
                            <span className="task-modal__reward-name">{userStatus.currentReward.name || ''}</span>
                          </div>
                        </div>
                      ) : null}
                    </>
                  ) : (
                    // Для обычных заданий используем стандартные поля
                    <>
                      {task.reward_type === 'currency' && task.reward_amount && (
                        <div className="task-modal__reward-currency">
                          <span className="task-modal__reward-amount">{task.reward_amount.toLocaleString('ru-RU')}</span>
                          <span className="task-modal__reward-icon task-modal__reward-icon--coin">
                            <span className="icons icons_16px icons_16px_coin"></span>
                          </span>
                        </div>
                      )}
                      {task.reward_type === 'item' && task.reward_item && (
                        <div className="task-modal__reward-item">
                          {task.reward_item.image && (
                            <div className="task-modal__reward-image-wrapper">
                              <img
                                src={task.reward_item.image}
                                alt={task.reward_item.name}
                                className="task-modal__reward-image"
                              />
                            </div>
                          )}
                          <div className="task-modal__reward-info">
                            <span className="task-modal__reward-name">{task.reward_item.name}</span>
                          </div>
                        </div>
                      )}
                    </>
                  )}
                </div>

                {/* Статус */}
                {userStatus && (
                  <div className="task-modal__status-block">
                    {isCompleted && (
                      <div className="task-modal__status-message task-modal__status-message--success">
                        <Icon name="check" fontSize="medium" />
                        <span>Задание выполнено</span>
                      </div>
                    )}
                    {isLimitReached && (
                      <div className="task-modal__status-message task-modal__status-message--warning">
                        <Icon name="info" fontSize="medium" />
                        <span>{userStatus.message}</span>
                      </div>
                    )}
                    {userStatus.status === 'unavailable' && (
                      <div className="task-modal__status-message task-modal__status-message--error">
                        <Icon name="close" fontSize="medium" />
                        <span>{userStatus.message}</span>
                      </div>
                    )}
                  </div>
                )}
              </div>
            </div>

            <footer className="product-modal__footer">
              {error && (
                <div className="product-modal__error-message">
                  {error}
                </div>
              )}

              <div className="product-modal__purchase-actions" style={{ display: 'flex', gap: '12px', justifyContent: 'flex-end' }}>
                <Button
                  onClick={onClose}
                  variant="secondary"
                  disabled={checking}
                >
                  Закрыть
                </Button>
                {canCheck && (
                  <Button
                    onClick={handleCheck}
                    variant="primary"
                    loading={checking}
                    disabled={checking}
                    rightIcon="check"
                  >
                    {task.button_text || 'Выполнить'}
                  </Button>
                )}
              </div>
            </footer>
          </>
        ) : null}
      </div>
    </div>
  );
}

