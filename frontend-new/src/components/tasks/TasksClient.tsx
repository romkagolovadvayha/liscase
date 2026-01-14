'use client';

import React, { useState, useEffect, useRef, useCallback } from 'react';
import type { Task, TaskStats } from '@/types/tasks';
import TaskCard from './TaskCard';
import TaskStatsDisplay from './TaskStatsDisplay';
import TaskModal from './TaskModal';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';
import { useRouter } from 'next/navigation';
import Button from '@/components/forms/Button';

const TASKS_PER_PAGE = 20;

type TaskFilter = 'all' | 'active' | 'completed' | 'vip';

export default function TasksClient() {
  const router = useRouter();
  const [tasks, setTasks] = useState<Task[]>([]);
  const [stats, setStats] = useState<TaskStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [selectedTaskId, setSelectedTaskId] = useState<number | null>(null);
  const [filter, setFilter] = useState<TaskFilter>('all');
  const observerTarget = useRef<HTMLDivElement>(null);

  // Проверяем авторизацию
  useEffect(() => {
    if (typeof window !== 'undefined' && !isAuthenticated()) {
      const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://api.test.prostoj.store';
      window.location.href = `${apiBaseUrl}/v1/auth/oauth`;
      return;
    }
  }, []);

  // Загрузка заданий
  const loadTasks = useCallback(async (page: number, append: boolean = false) => {
    if (typeof window === 'undefined' || !isAuthenticated()) {
      return;
    }

    if (append && (isLoadingMore || !hasMore)) return;

    if (append) {
      setIsLoadingMore(true);
    } else {
      setLoading(true);
    }

    try {
      const response = await apiClient.get('/tasks', {
        params: {
          page: page.toString(),
          pageSize: TASKS_PER_PAGE.toString(),
        },
      });
      const result = response.data;

      if (result.success && result.data) {
        const tasksData = result.data.tasks || [];
        const statistics = result.data.statistics || null;
        const pagination = result.data.pagination || null;

        // Фильтрация на клиенте
        let filteredTasks = tasksData;
        if (filter === 'active') {
          filteredTasks = tasksData.filter(
            (task: Task) => task.userStatus?.status === 'available' || task.userStatus?.status === 'in_progress'
          );
        } else if (filter === 'completed') {
          filteredTasks = tasksData.filter((task: Task) => task.userStatus?.status === 'completed');
        } else if (filter === 'vip') {
          filteredTasks = tasksData.filter((task: Task) => task.is_vip_only === true);
        }

        if (append) {
          setTasks((prev) => [...prev, ...filteredTasks]);
        } else {
          setTasks(filteredTasks);
        }

        // Статистика только с первой страницы
        if (!append && statistics) {
          setStats(statistics);
        }

        setCurrentPage(page);
        
        if (pagination) {
          // Если отфильтровали и осталось меньше, чем было, нужно скорректировать hasMore
          const hasMoreAfterFilter = filteredTasks.length > 0 || page < pagination.totalPages;
          setHasMore(hasMoreAfterFilter && page < pagination.totalPages);
        } else {
          setHasMore(false);
        }
      } else {
        console.error('Failed to load tasks:', result.error);
        if (!append) {
          setTasks([]);
          setStats(null);
        }
        setHasMore(false);
      }
    } catch (error: any) {
      console.error('Error loading tasks:', error);
      if (error.response?.status === 401) {
        // Не авторизован - редиректим на авторизацию
        const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://api.test.prostoj.store';
        window.location.href = `${apiBaseUrl}/v1/auth/oauth`;
      } else {
        if (!append) {
          setTasks([]);
          setStats(null);
        }
        setHasMore(false);
      }
    } finally {
      if (append) {
        setIsLoadingMore(false);
      } else {
        setLoading(false);
      }
    }
  }, [isLoadingMore, hasMore, filter]);

  // Загрузка следующей страницы
  const loadMoreTasks = useCallback(() => {
    if (!isLoadingMore && hasMore) {
      loadTasks(currentPage + 1, true);
    }
  }, [currentPage, hasMore, isLoadingMore, loadTasks]);

  // Первоначальная загрузка и при изменении фильтра
  useEffect(() => {
    if (typeof window !== 'undefined' && isAuthenticated()) {
      setTasks([]);
      setCurrentPage(1);
      setHasMore(true);
      loadTasks(1, false);
    }
  }, [filter]); // eslint-disable-line react-hooks/exhaustive-deps

  // Intersection Observer для бесконечной прокрутки
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && hasMore && !isLoadingMore && !loading) {
          loadMoreTasks();
        }
      },
      { threshold: 0.1 }
    );

    const currentTarget = observerTarget.current;
    if (currentTarget) {
      observer.observe(currentTarget);
    }

    return () => {
      if (currentTarget) {
        observer.unobserve(currentTarget);
      }
    };
  }, [hasMore, isLoadingMore, loading, loadMoreTasks]);

  const handleTaskClick = (taskId: number) => {
    setSelectedTaskId(taskId);
  };

  const handleTaskCompleted = () => {
    // Перезагружаем список задач с первой страницы
    loadTasks(1, false);
  };

  const handleModalClose = () => {
    setSelectedTaskId(null);
  };

  const handleFilterChange = (newFilter: TaskFilter) => {
    setFilter(newFilter);
  };

  return (
    <div className="tasks-page">
      <div className="tasks-container">
        <div className="tasks-header">
          <h1>Задания</h1>
          {stats && <TaskStatsDisplay stats={stats} />}
        </div>
        
        {/* Фильтры */}
        <div className="tasks-filters">
          <Button
            onClick={() => handleFilterChange('all')}
            variant={filter === 'all' ? 'primary' : 'secondary'}
            size="small"
          >
            Все
          </Button>
          <Button
            onClick={() => handleFilterChange('active')}
            variant={filter === 'active' ? 'primary' : 'secondary'}
            size="small"
          >
            Активные
          </Button>
          <Button
            onClick={() => handleFilterChange('completed')}
            variant={filter === 'completed' ? 'primary' : 'secondary'}
            size="small"
          >
            Выполненные
          </Button>
          <Button
            onClick={() => handleFilterChange('vip')}
            variant={filter === 'vip' ? 'primary' : 'secondary'}
            size="small"
          >
            VIP
          </Button>
        </div>

        <div className="tasks-list">
          {loading ? (
            // Skeleton во время загрузки
            <>
              {Array.from({ length: 8 }).map((_, index) => (
                <div key={`skeleton-${index}`} className="task-card-skeleton">
                  <div className="task-card-skeleton__image"></div>
                  <div className="task-card-skeleton__title"></div>
                  <div className="task-card-skeleton__reward"></div>
                </div>
              ))}
            </>
          ) : tasks.length === 0 ? (
            <div className="tasks-empty">
              <p>Нет доступных заданий</p>
            </div>
          ) : (
            <>
              {tasks.map((task) => (
                <TaskCard
                  key={task.id}
                  task={task}
                  onClick={handleTaskClick}
                />
              ))}
              {/* Элемент для отслеживания скролла */}
              {hasMore && (
                <div ref={observerTarget} style={{ minHeight: '20px', width: '100%', gridColumn: '1 / -1' }}>
                  {isLoadingMore && (
                    <div className="tasks-loading-more">
                      <p>Загрузка заданий...</p>
                    </div>
                  )}
                </div>
              )}
            </>
          )}
        </div>
      </div>
      <TaskModal
        taskId={selectedTaskId}
        isOpen={selectedTaskId !== null}
        onClose={handleModalClose}
        onTaskCompleted={handleTaskCompleted}
      />
    </div>
  );
}
