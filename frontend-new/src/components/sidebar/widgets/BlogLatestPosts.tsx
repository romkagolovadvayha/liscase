'use client';

import React from 'react';
import BlogLatestPostCard from './BlogLatestPostCard';
import { useBlogPosts, type BlogPost } from '@/hooks/useBlogPosts';

export default function BlogLatestPosts() {
  const { data: blogData, isLoading: loading } = useBlogPosts({
    limit: 5,
    sort: 'created_at',
    order: 'desc',
  });

  const posts = blogData?.data?.posts || [];

  if (loading) {
    return (
      <section className="sidebar__widget blog-latest-posts">
        <h4 className="blog-latest-posts__title">Последние новости</h4>
        <ul className="blog-latest-posts__list">
          {[1, 2, 3, 4, 5].map((i) => (
            <li key={i} className="blog-latest-post">
              <div className="blog-latest-post__image" style={{ width: 60, height: 60, backgroundColor: 'var(--background-hover)', borderRadius: 'var(--card-radius)' }}></div>
              <div className="blog-latest-post__content">
                <div style={{ height: 16, backgroundColor: 'var(--background-hover)', borderRadius: 4, marginBottom: 8 }}></div>
                <div style={{ height: 12, backgroundColor: 'var(--background-hover)', borderRadius: 4, width: '60%' }}></div>
              </div>
            </li>
          ))}
        </ul>
      </section>
    );
  }

  if (posts.length === 0) {
    return null;
  }

  return (
    <section className="sidebar__widget blog-latest-posts">
      <h4 className="blog-latest-posts__title">Последние новости</h4>
      <ul className="blog-latest-posts__list">
        {posts.map((post) => {
          // Формируем URL поста
          let postUrl = post.url || `/posts/post-${post.link_name || post.id}`;
          if (!postUrl.startsWith('/')) {
            postUrl = `/posts/${postUrl}`;
          }

          return (
            <BlogLatestPostCard
              key={post.id}
              id={post.id}
              title={post.title}
              image={post.image}
              views={post.views}
              commentsCount={post.commentsCount}
              url={postUrl}
            />
          );
        })}
      </ul>
    </section>
  );
}

