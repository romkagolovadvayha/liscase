'use client';

import React from 'react';
import { Article } from '@mui/icons-material';
import { useTableOfContents } from '@/contexts/TableOfContentsContext';
import '@/styles/blog.scss';

export default function BlogTableOfContents() {
  const { items } = useTableOfContents();

  if (items.length === 0) {
    return null;
  }

  const scrollToHeading = (id: string) => {
    const element = document.getElementById(id);
    if (element) {
      element.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  };

  return (
    <section className="sidebar__widget stat-block">
      <h4 className="stat-block__title">
        <span>
          <Article className="blog-post__toc-icon" />
          Содержание
        </span>
      </h4>
      <nav className="blog-post__toc">
        <ul className="blog-post__toc-list">
          {items.map((item) => (
            <li
              key={item.id}
              className={`blog-post__toc-item blog-post__toc-item--level-${item.level}`}
            >
              <button
                onClick={() => scrollToHeading(item.id)}
                className="blog-post__toc-link"
              >
                {item.text}
              </button>
            </li>
          ))}
        </ul>
      </nav>
    </section>
  );
}

