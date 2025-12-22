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

  moment.locale('ru');

  // Загрузка комментариев
  const loadComments = useCallback(async () => {
    setLoading(true);
    try {
      const response = await fetch(`/api/blog/posts/${blogId}/comments`);
      const result = await response.json();

      if (result.success) {
        const commentsData = result.data || [];
        // Логируем для отладки
        if (commentsData.length > 0) {
          console.log('First comment avatar:', commentsData[0].avatar);
        }
        setComments(commentsData);
        setVisibleCommentsCount(5); // Сбрасываем счетчик при загрузке
      }
    } catch (err: any) {
      console.error('Error loading comments:', err);
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

    setSubmitting(true);
    try {
      const response = await fetch(`/api/blog/posts/${blogId}/comments`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          content: newComment.trim(),
          parentId: null,
        }),
      });

      const result = await response.json();

      if (result.success) {
        setNewComment('');
        await loadComments();
      } else {
        alert(result.message || 'Ошибка при добавлении комментария');
      }
    } catch (err: any) {
      console.error('Error submitting comment:', err);
      alert('Ошибка при добавлении комментария');
    } finally {
      setSubmitting(false);
    }
  };

  // Отправка ответа
  const handleReply = async (parentId: number) => {
    if (!replyContent.trim() || submitting) return;

    setSubmitting(true);
    try {
      const response = await fetch(`/api/blog/posts/${blogId}/comments`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          content: replyContent.trim(),
          parentId,
        }),
      });

      const result = await response.json();

      if (result.success) {
        setReplyContent('');
        setReplyingTo(null);
        await loadComments();
      } else {
        alert(result.message || 'Ошибка при добавлении ответа');
      }
    } catch (err: any) {
      console.error('Error submitting reply:', err);
      alert('Ошибка при добавлении ответа');
    } finally {
      setSubmitting(false);
    }
  };

  // Лайк/дизлайк комментария
  const handleLike = async (commentId: number, currentIsLiked: boolean) => {
    try {
      const response = await fetch(`/api/blog/comments/${commentId}/like`, {
        method: 'POST',
      });

      const result = await response.json();

      if (result.success) {
        // Обновляем состояние комментария
        const updateComment = (comments: Comment[]): Comment[] => {
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
                replies: updateComment(comment.replies),
              };
            }
            return comment;
          });
        };

        setComments(updateComment(comments));
      }
    } catch (err: any) {
      console.error('Error toggling like:', err);
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
    const maxDepth = 3;
    const canReply = depth < maxDepth - 1;

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
            
            {canReply && (
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
          </div>

          {/* Форма ответа */}
          {replyingTo === comment.id && (
            <div className="blog-comment__reply-form">
              <textarea
                className="blog-comment__textarea"
                value={replyContent}
                onChange={(e) => setReplyContent(e.target.value)}
                placeholder="Написать ответ..."
                rows={3}
                maxLength={5000}
              />
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
      <form onSubmit={handleSubmit} className="blog-comment__form">
        <textarea
          className="blog-comment__textarea"
          value={newComment}
          onChange={(e) => setNewComment(e.target.value)}
          placeholder="Написать комментарий..."
          rows={4}
          maxLength={5000}
        />
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

