import React from 'react';
import Link from 'next/link';
import { Avatar } from 'antd';

export interface BlogPost {
  id: number;
  slug: string;
  title: string;
  previewImage?: string;
  views?: number;
  commentsCount?: number;
  url?: string;
}

export interface BlogPopularPostsProps {
  posts: BlogPost[];
}

export default function BlogPopularPosts({ posts }: BlogPopularPostsProps) {
  return (
    <section className="sidebar__widget stat-block">
      <h4 className="stat-block__title">Читают сейчас</h4>
      <ul className="stat-block__list">
        {posts.map((post) => (
          <li key={post.id} className="stat-block__list-item">
            {post.previewImage && (
              <div className="stat-block__list-avatar">
                <Avatar
                  src={post.previewImage}
                  alt={post.title}
                  className="stat-block__list-avatar-img"
                  size="default"
                  shape="square"
                />
              </div>
            )}
            <div className="stat-block__list-content">
              <Link href={post.url || `/posts`} className="stat-block__list-link">
                {post.title}
              </Link>
              <div className="stat-block__list-footer">
                {post.views !== undefined && (
                  <span className="stat-block__list-meta" title="Количество просмотров">
                    <i className="fas fa-eye"></i>
                    <span>{post.views}</span>
                  </span>
                )}
                {post.commentsCount !== undefined && (
                  <Link
                    href={`${post.url || `/posts`}#comments`}
                    className="stat-block__list-meta"
                    title="Количество комментариев"
                  >
                    <i className="fas fa-comments"></i>
                    <span>{post.commentsCount}</span>
                  </Link>
                )}
              </div>
            </div>
          </li>
        ))}
      </ul>
    </section>
  );
}

