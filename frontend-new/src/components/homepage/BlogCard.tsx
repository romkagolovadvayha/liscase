'use client';

import React from 'react';
import Link from 'next/link';
import Icon from '@/components/icons/Icon';
import moment from 'moment';
import 'moment/locale/ru';

interface BlogCardProps {
  id: number;
  title: string;
  description: string;
  image?: string;
  category?: string;
  date: string;
  url: string;
}

export default function BlogCard({ title, description, image, category, date, url }: BlogCardProps) {
  moment.locale('ru');

  const formatDate = (dateString: string): string => {
    try {
      const date = moment(dateString);
      if (!date.isValid()) {
        return dateString; // Возвращаем исходную строку, если не удалось распарсить
      }
      return date.format('DD.MM.YYYY');
    } catch {
      return dateString;
    }
  };

  return (
    <article className="home-blog-card">
      {image && (
        <div className="home-blog-card_image">
          <Link href={url}>
            <img src={image} alt={title} loading="lazy" />
            <div className="home-blog-card_image_overlay">
              <Icon name="arrow-right" fontSize="large" />
            </div>
          </Link>
        </div>
      )}

      <div className="home-blog-card_content">
        <div className="home-blog-card_meta">
          <div className="home-blog-card_date">
            <Icon name="calendar" fontSize="small" />
            <time>{formatDate(date)}</time>
          </div>
          {category && (
            <div className="home-blog-card_category">
              <Icon name="tag" fontSize="small" />
              <span>{category}</span>
            </div>
          )}
        </div>

        <Link href={url} className="home-blog-card_title-link">
          <h3 className="home-blog-card_title">{title}</h3>
        </Link>

        <p className="home-blog-card_excerpt">{description}</p>

        <Link href={url} className="home-blog-card_read-more">
          Читать далее
          <Icon name="arrow-right" fontSize="small" />
        </Link>
      </div>
    </article>
  );
}

