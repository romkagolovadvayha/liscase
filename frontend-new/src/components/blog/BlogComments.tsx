'use client';

import React, { useState, useEffect, useCallback } from 'react';
import { Avatar, Spin, Empty } from 'antd';
import { LikeOutlined, LikeFilled, MessageOutlined, SmileOutlined } from '@ant-design/icons';
import data from '@emoji-mart/data';
import Picker from '@emoji-mart/react';
import Button from '@/components/forms/Button';
import Input from '@/components/forms/Input';
import moment from 'moment';
import 'moment/locale/ru';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';
import '@/styles/blog.scss';

interface Comment {
  id: number;
  content: string;
  parentId: number | null;
  level: number;
  userId: number;
  username: string;
  steamId: string;
  avatar: string;
  createdAt: number;
  updatedAt: number;
  likesCount: number;
  isLiked: boolean;
  canReply?: boolean;
  replies: Comment[];
}

interface BlogCommentsProps {
  blogId: number | string; // Может быть ID (число) или link_name (строка)
}

export default function BlogComments({ blogId }: BlogCommentsProps) {
  const [comments, setComments] = useState<Comment[]>([]);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [newComment, setNewComment] = useState('');
  const [replyingTo, setReplyingTo] = useState<number | null>(null);
  const [replyContent, setReplyContent] = useState('');
  const [showEmojiPicker, setShowEmojiPicker] = useState<number | null>(null);
  const [showReplyEmojiPicker, setShowReplyEmojiPicker] = useState(false);
  const [visibleCommentsCount, setVisibleCommentsCount] = useState(5);
  const [error, setError] = useState<string | null>(null);
  const [replyError, setReplyError] = useState<string | null>(null);

  moment.locale('ru');
  const userIsAuthenticated = isAuthenticated();

  // Загрузка комментариев
  const loadComments = useCallback(async () => {
    setLoading(true);
    try {
      const response = await apiClient.get(`/blog/${blogId}/comments`);
      const result = response.data;

      if (result.success && result.data?.comments) {
        // Преобразуем плоский массив в древовидную структуру
        const buildCommentTree = (commentData: any): Comment => {
          return {
            id: commentData.id,
            content: commentData.content,
            parentId: commentData.parentId,
            level: commentData.level,
            userId: commentData.userId,
            username: commentData.username,
            steamId: commentData.steamId,
            avatar: commentData.avatar,
            createdAt: commentData.createdAt,
            updatedAt: commentData.updatedAt,
            likesCount: commentData.likesCount,
            isLiked: commentData.isLiked,
            canReply: commentData.canReply !== undefined ? commentData.canReply : (commentData.level < 2),
            replies: (commentData.replies || []).map(buildCommentTree),
          };
        };

        const commentsTree = result.data.comments.map(buildCommentTree);
        setComments(commentsTree);
      } else {
        setComments([]);
      }
      setVisibleCommentsCount(5);
    } catch (err: any) {
      console.error('Error loading comments:', err);
      setComments([]);
    } finally {
      setLoading(false);
    }
  }, [blogId]);

  useEffect(() => {
    loadComments();
  }, [loadComments]);

  // Отправка комментария
  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    
    if (!newComment.trim() || submitting) return;

    setError(null);
    setSubmitting(true);
    try {
      const response = await apiClient.post(`/blog/${blogId}/comments`, {
        content: newComment.trim(),
      });
      const result = response.data;

      if (result.success && result.data?.comment) {
        // Перезагружаем комментарии
        await loadComments();
        setNewComment('');
        setError(null);
      } else {
        // Извлекаем сообщения об ошибках из details
        const errorDetails = result.error?.details || {};
        const errorMessages: string[] = [];
        Object.keys(errorDetails).forEach(key => {
          if (Array.isArray(errorDetails[key])) {
            errorMessages.push(...errorDetails[key]);
          } else {
            errorMessages.push(errorDetails[key]);
          }
        });
        const errorMessage = errorMessages.length > 0 
          ? errorMessages.join('. ') 
          : (result.error?.message || 'Ошибка при добавлении комментария');
        setError(errorMessage);
      }
    } catch (err: any) {
      console.error('Error submitting comment:', err);
      if (err.response?.status === 401) {
        setError('Требуется авторизация');
      } else {
        const errorDetails = err.response?.data?.error?.details || {};
        const errorMessages: string[] = [];
        Object.keys(errorDetails).forEach(key => {
          if (Array.isArray(errorDetails[key])) {
            errorMessages.push(...errorDetails[key]);
          } else {
            errorMessages.push(errorDetails[key]);
          }
        });
        const errorMessage = errorMessages.length > 0 
          ? errorMessages.join('. ') 
          : (err.response?.data?.error?.message || err.message || 'Ошибка при добавлении комментария');
        setError(errorMessage);
      }
    } finally {
      setSubmitting(false);
    }
  };

  // Отправка ответа
  const handleReply = async (parentId: number) => {
    if (!replyContent.trim() || submitting) return;

    setReplyError(null);
    setSubmitting(true);
    try {
      const response = await apiClient.post(`/blog/${blogId}/comments`, {
        content: replyContent.trim(),
        parentId: parentId,
      });
      const result = response.data;
      console.log('Reply response:', result); // Логируем ответ для отладки

      if (result.success && result.data?.comment) {
        // Перезагружаем комментарии
        await loadComments();
        setReplyContent('');
        setReplyingTo(null);
        setReplyError(null);
      } else {
        console.error('Invalid response structure:', result);
        // Извлекаем сообщения об ошибках из details
        const errorDetails = result.error?.details || {};
        const errorMessages: string[] = [];
        Object.keys(errorDetails).forEach(key => {
          if (Array.isArray(errorDetails[key])) {
            errorMessages.push(...errorDetails[key]);
          } else {
            errorMessages.push(errorDetails[key]);
          }
        });
        const errorMessage = errorMessages.length > 0 
          ? errorMessages.join('. ') 
          : (result.error?.message || result.message || 'Ошибка при добавлении ответа');
        setReplyError(errorMessage);
      }
    } catch (err: any) {
      console.error('Error submitting reply:', err);
      if (err.response?.status === 401) {
        setReplyError('Требуется авторизация');
      } else {
        const errorDetails = err.response?.data?.error?.details || {};
        const errorMessages: string[] = [];
        Object.keys(errorDetails).forEach(key => {
          if (Array.isArray(errorDetails[key])) {
            errorMessages.push(...errorDetails[key]);
          } else {
            errorMessages.push(errorDetails[key]);
          }
        });
        const errorMessage = errorMessages.length > 0 
          ? errorMessages.join('. ') 
          : (err.response?.data?.error?.message || err.message || 'Ошибка при добавлении ответа');
        setReplyError(errorMessage);
      }
    } finally {
      setSubmitting(false);
    }
  };

  // Лайк/дизлайк комментария
  const handleLike = async (commentId: number, currentIsLiked: boolean) => {
    if (!userIsAuthenticated) {
      const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://api.test.prostoj.store';
      window.location.href = `${apiBaseUrl}/v1/auth/oauth`;
      return;
    }

    try {
      const response = await apiClient.post(`/blog/comments/${commentId}/like`);
      const result = response.data;

      if (result.success && result.data) {
        // Обновляем состояние комментария в массиве
        const updateCommentLike = (comments: Comment[]): Comment[] => {
          return comments.map(comment => {
            if (comment.id === commentId) {
              return {
                ...comment,
                isLiked: result.data.isLiked,
                likesCount: result.data.likesCount,
              };
            }
            if (comment.replies.length > 0) {
              return {
                ...comment,
                replies: updateCommentLike(comment.replies),
              };
            }
            return comment;
          });
        };

        setComments(prev => updateCommentLike(prev));
      }
    } catch (err: any) {
      console.error('Error toggling like:', err);
      if (err.response?.status === 401) {
        const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://api.test.prostoj.store';
        window.location.href = `${apiBaseUrl}/v1/auth/oauth`;
      }
    }
  };

  // Форматирование даты
  const formatDate = (timestamp: number): string => {
    return moment.unix(timestamp).fromNow();
  };

  // Добавление эмодзи
  const addEmoji = (emoji: any, inputRef: 'main' | 'reply') => {
    const emojiText = emoji.native || '';
    
    if (inputRef === 'main') {
      setNewComment(prev => prev + emojiText);
      setShowEmojiPicker(null);
    } else {
      setReplyContent(prev => prev + emojiText);
      setShowReplyEmojiPicker(false);
    }
  };

  // Рендер одного комментария (рекурсивно)
  const renderComment = (comment: Comment, depth: number = 0) => {
    // Используем canReply из API ответа (уровень комментария < 2)
    const canReply = comment.canReply !== undefined ? comment.canReply : (comment.level < 2);

    return (
      <div key={comment.id} className={`blog-comment ${depth > 0 ? 'blog-comment--reply' : ''}`} style={{ marginLeft: depth * 40 }}>
        <div className="blog-comment__content">
          <div className="blog-comment__header">
            <Avatar 
              src={comment.avatar} 
              alt={comment.username} 
              className="blog-comment__avatar" 
              size={40}
            />
            <div className="blog-comment__info">
              <span className="blog-comment__username">{comment.username}</span>
              <span className="blog-comment__date">{formatDate(comment.createdAt)}</span>
            </div>
          </div>
          
          <div className="blog-comment__text">{comment.content}</div>
          
          <div className="blog-comment__actions">
            <button
              className={`blog-comment__like ${comment.isLiked ? 'blog-comment__like--active' : ''}`}
              onClick={() => handleLike(comment.id, comment.isLiked)}
            >
              {comment.isLiked ? <LikeFilled /> : <LikeOutlined />}
              <span>{comment.likesCount}</span>
            </button>
            
            {canReply && userIsAuthenticated && (
              <button
                className="blog-comment__reply"
                onClick={() => {
                  setReplyingTo(comment.id);
                  setShowReplyEmojiPicker(false);
                }}
              >
                <MessageOutlined />
                <span>Ответить</span>
              </button>
            )}
            {canReply && !userIsAuthenticated && (
              <button
                className="blog-comment__reply"
                onClick={() => {
                  const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://api.test.prostoj.store';
                  window.location.href = `${apiBaseUrl}/v1/auth/oauth`;
                }}
              >
                <MessageOutlined />
                <span>Авторизоваться для ответа</span>
              </button>
            )}
          </div>

          {/* Форма ответа */}
          {replyingTo === comment.id && (
            <div className="blog-comment__reply-form">
              <textarea
                className={`blog-comment__textarea ${replyError ? 'blog-comment__textarea--error' : ''}`}
                value={replyContent}
                onChange={(e) => {
                  setReplyContent(e.target.value);
                  if (replyError) setReplyError(null);
                }}
                placeholder="Написать ответ..."
                rows={3}
                maxLength={5000}
              />
              {replyError && (
                <div className="blog-comment__error">
                  {replyError}
                </div>
              )}
              {replyContent.length > 0 && (
                <div className="blog-comment__char-count">
                  {replyContent.length} / 5000
                </div>
              )}
              <div className="blog-comment__reply-form-actions">
                <div className="blog-comment__emoji-wrapper">
                  <button
                    type="button"
                    className="blog-comment__emoji-btn"
                    onClick={() => setShowReplyEmojiPicker(!showReplyEmojiPicker)}
                  >
                    <SmileOutlined />
                  </button>
                  {showReplyEmojiPicker && (
                    <div className="blog-comment__emoji-picker">
                      <Picker
                        data={data}
                        onEmojiSelect={(emoji: any) => addEmoji(emoji, 'reply')}
                        theme="dark"
                        locale="ru"
                      />
                    </div>
                  )}
                </div>
                <div className="blog-comment__reply-buttons">
                  <Button
                    variant="secondary"
                    size="small"
                    onClick={() => {
                      setReplyingTo(null);
                      setReplyContent('');
                      setReplyError(null);
                    }}
                  >
                    Отмена
                  </Button>
                  <Button
                    variant="primary"
                    size="small"
                    rightIcon="paper-plane"
                    onClick={() => handleReply(comment.id)}
                    loading={submitting}
                    disabled={!replyContent.trim()}
                  >
                    Отправить
                  </Button>
                </div>
              </div>
            </div>
          )}
        </div>

        {/* Вложенные комментарии */}
        {comment.replies.length > 0 && (
          <div className="blog-comment__replies">
            {comment.replies.map((reply) => renderComment(reply, depth + 1))}
          </div>
        )}
      </div>
    );
  };

  if (loading) {
    return (
      <section className="blog-post__comments">
        <h2 className="blog-post__comments-title">Комментарии</h2>
        <div className="blog-post__comments-loading">
          <Spin size="large" />
        </div>
      </section>
    );
  }

  return (
    <section className="blog-post__comments">
      <h2 className="blog-post__comments-title">Комментарии</h2>

      {/* Форма добавления комментария */}
      {userIsAuthenticated ? (
        <form onSubmit={handleSubmit} className="blog-comment__form">
        <textarea
          className={`blog-comment__textarea ${error ? 'blog-comment__textarea--error' : ''}`}
          value={newComment}
          onChange={(e) => {
            setNewComment(e.target.value);
            if (error) setError(null);
          }}
          placeholder="Написать комментарий..."
          rows={4}
          maxLength={5000}
        />
        {error && (
          <div className="blog-comment__error">
            {error}
          </div>
        )}
        {newComment.length > 0 && (
          <div className="blog-comment__char-count">
            {newComment.length} / 5000
          </div>
        )}
        <div className="blog-comment__form-actions">
          <div className="blog-comment__emoji-wrapper">
            <button
              type="button"
              className="blog-comment__emoji-btn"
              onClick={() => setShowEmojiPicker(showEmojiPicker ? null : 0)}
            >
              <SmileOutlined />
            </button>
            {showEmojiPicker !== null && (
              <div className="blog-comment__emoji-picker">
                <Picker
                  data={data}
                  onEmojiSelect={(emoji: any) => addEmoji(emoji, 'main')}
                  theme="dark"
                  locale="ru"
                />
              </div>
            )}
          </div>
          <Button
            type="submit"
            variant="primary"
            size="small"
            rightIcon="paper-plane"
            disabled={!newComment.trim() || submitting}
            loading={submitting}
          >
            Отправить
          </Button>
        </div>
      </form>
      ) : (
        <div className="blog-comment__auth-required">
          <p className="blog-comment__auth-text">
            Чтобы оставлять комментарии, необходимо{' '}
            <a
              href={`${process.env.NEXT_PUBLIC_API_BASE_URL || 'http://api.test.prostoj.store'}/v1/auth/oauth`}
              className="blog-comment__auth-link"
            >
              авторизоваться
            </a>
          </p>
        </div>
      )}

      {/* Список комментариев */}
      {comments.length === 0 ? (
        <div className="blog-comment__empty">
          <p className="blog-comment__empty-text">Пока нет комментариев. Будьте первым!</p>
        </div>
      ) : (
        <>
          <div className="blog-comments__list">
            {comments.slice(0, visibleCommentsCount).map((comment) => renderComment(comment))}
          </div>
          {comments.length > visibleCommentsCount && (
            <div className="blog-comments__show-more">
              <Button
                variant="secondary"
                size="small"
                onClick={() => setVisibleCommentsCount(prev => Math.min(prev + 5, comments.length))}
              >
                Показать еще ({comments.length - visibleCommentsCount})
              </Button>
            </div>
          )}
        </>
      )}
    </section>
  );
}

