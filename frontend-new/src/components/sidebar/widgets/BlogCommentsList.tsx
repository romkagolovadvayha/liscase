'use client';

import React from 'react';
import Link from 'next/link';
import { Avatar } from 'antd';
import moment from 'moment';
import 'moment/locale/ru';

export interface Comment {
  id: number;
  username: string;
  avatar?: string;
  blogTitle: string;
  blogUrl: string;
  createdAt: string;
  text?: string;
}

export interface BlogCommentsListProps {
  comments: Comment[];
}

export default function BlogCommentsList({ comments }: BlogCommentsListProps) {
  moment.locale('ru');

  const formatDate = (dateString: string): string => {
    try {
      const date = moment(dateString);
      if (!date.isValid()) {
        return dateString; // Возвращаем исходную строку, если не удалось распарсить
      }
      if (moment().diff(date, 'days') < 1) {
        return date.fromNow();
      }
      return date.format('DD.MM.YYYY HH:mm');
    } catch {
      return dateString;
    }
  };

  return (
    <section className="sidebar__widget stat-block">
      <h4 className="stat-block__title">Последние комментарии</h4>
      <ul className="stat-block__list">
        {comments.map((comment) => (
          <li key={comment.id} className="stat-block__list-item">
            <div className="stat-block__list-avatar">
              <Avatar
                src={comment.avatar || '/images/default-avatar.png'}
                alt={comment.username}
                className="stat-block__list-avatar-img"
                size="default"
              />
            </div>
            <div className="stat-block__list-content">
              <p className="stat-block__list-text">
                Пользователь <b>{comment.username}</b> оставил комментарий к записи{' '}
                <Link href={comment.blogUrl}>{comment.blogTitle}</Link>
              </p>
              <div className="stat-block__list-footer">
                <span className="stat-block__list-meta" title="Дата комментария">
                  {formatDate(comment.createdAt)}
                </span>
              </div>
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}

