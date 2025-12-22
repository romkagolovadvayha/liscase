'use client';

import React, { useEffect, useState } from 'react';
import BlogLatestPostCard from './BlogLatestPostCard';

export interface BlogPost {
  id: number;
  title: string;
  image?: string;
  views?: number;
  commentsCount?: number;
  url?: string;
  link_name?: string;
}

export default function BlogLatestPosts() {
  const [posts, setPosts] = useState<BlogPost[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchLatestPosts = async () => {
      try {
        const response = await fetch('/api/blog?limit=5&sort=created_at&order=desc');
        const result = await response.json();
        
        if (result.success && result.data?.posts) {
          setPosts(result.data.posts);
        }
      } catch (error) {
        console.error('Error fetching latest posts:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchLatestPosts();
  }, []);

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

