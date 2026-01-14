'use client';

import React from 'react';
import Link from 'next/link';
import { Avatar } from 'antd';
import moment from 'moment';
import 'moment/locale/ru';
import '@/styles/blog.scss';

interface BlogMiniCardProps {
  id: number;
  title: string;
  description: string;
  image?: string;
  category?: string;
  date: string;
  url: string;
}

export default function BlogMiniCard({ title, description, image, category, date, url }: BlogMiniCardProps) {
  moment.locale('ru');

  const formatDate = (dateString: string): string => {
    try {
      const date = moment(dateString);
      if (!date.isValid()) {
        return dateString;
      }
      return date.format('DD.MM.YYYY');
    } catch {
      return dateString;
    }
  };

  return (
    <article className="blog-mini-card">
      <Link href={url} className="blog-mini-card__link">
        {image && (
          <div className="blog-mini-card__image">
            <Avatar
              src={image}
              alt={title}
              shape="square"
              size={80}
              className="blog-mini-card__avatar"
            />
          </div>
        )}
        <div className="blog-mini-card__content">
          <h4 className="blog-mini-card__title">{title}</h4>
          {description && (
            <p className="blog-mini-card__description">{description}</p>
          )}
          <div className="blog-mini-card__meta">
            <time className="blog-mini-card__date">{formatDate(date)}</time>
            {category && (
              <span className="blog-mini-card__category">{category}</span>
            )}
          </div>
        </div>
      </Link>
    </article>
  );
}












