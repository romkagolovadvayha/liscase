import React from 'react';
import Link from 'next/link';
import { Visibility, Comment } from '@mui/icons-material';
import BlogLatestPostImage from './BlogLatestPostImage';

export interface BlogLatestPostCardProps {
  id: number;
  title: string;
  image?: string;
  views?: number;
  commentsCount?: number;
  url: string;
}

export default function BlogLatestPostCard({
  id,
  title,
  image,
  views,
  commentsCount,
  url,
}: BlogLatestPostCardProps) {
  return (
    <li className="blog-latest-post">
      {image && <BlogLatestPostImage src={image} alt={title} />}
      <div className="blog-latest-post__content">
        <Link href={url} className="blog-latest-post__link">
          {title}
        </Link>
        <div className="blog-latest-post__footer">
          {views !== undefined && (
            <span className="blog-latest-post__meta" title="Количество просмотров">
              <Visibility className="blog-latest-post__meta-icon" />
              <span>{views.toLocaleString('ru-RU')}</span>
            </span>
          )}
          {commentsCount !== undefined && (
            <Link
              href={`${url}#comments`}
              className="blog-latest-post__meta"
              title="Количество комментариев"
            >
              <Comment className="blog-latest-post__meta-icon" />
              <span>{commentsCount}</span>
            </Link>
          )}
        </div>
      </div>
    </li>
  );
}












