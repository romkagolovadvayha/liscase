'use client';

import React, { useEffect, useState, useMemo, useCallback } from 'react';
import { useRouter, usePathname } from 'next/navigation';
import Link from 'next/link';
import { Breadcrumb, Tag, Skeleton, Result, Spin, Button as AntButton } from 'antd';
import { HomeOutlined, FolderOutlined } from '@ant-design/icons';
import { 
  CalendarToday, 
  Visibility, 
  Comment, 
  FolderOpen,
  Label
} from '@mui/icons-material';
import {
  FacebookShareButton,
  TwitterShareButton,
  VKShareButton,
  TelegramShareButton,
  WhatsappShareButton,
  FacebookIcon,
  TwitterIcon,
  VKIcon,
  TelegramIcon,
  WhatsappIcon,
} from 'react-share';
import { useTableOfContents } from '@/contexts/TableOfContentsContext';
import BlogMiniCard from '@/components/blog/BlogMiniCard';
import BlogComments from '@/components/blog/BlogComments';
import apiClient from '@/lib/api/client';
import '@/styles/blog.scss';

interface BlogPostPageProps {
  linkName?: string;
  slug?: string[];
}

interface BlogPost {
  id: number;
  title: string;
  description: string;
  content: string;
  keywords: string;
  image: string | null;
  images: string[];
  category: string;
  categoryLinkName: string;
  parentCategoryName: string | null;
  parentCategoryLinkName: string | null;
  date: string;
  views: number;
  commentsCount: number;
  url: string;
}

interface SimilarPost {
  id: number;
  title: string;
  description: string;
  image?: string;
  category?: {
    id: number;
    name: string;
    linkName: string;
  } | null;
  date?: string;
  createdAt?: string;
  url: string;
}

interface TableOfContentsItem {
  id: string;
  text: string;
  level: number;
}

export default function BlogPostPage({ linkName, slug }: BlogPostPageProps) {
  const router = useRouter();
  const pathname = usePathname();
  const { setItems } = useTableOfContents();
  const [post, setPost] = useState<BlogPost | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [similarPosts, setSimilarPosts] = useState<SimilarPost[]>([]);
  const [similarLoading, setSimilarLoading] = useState(false);

  // Загрузка похожих записей
  const loadSimilarPosts = useCallback(async (postLinkName: string | undefined) => {
    if (!postLinkName) {
      console.warn('Cannot load similar posts: linkName is undefined');
      return;
    }
    
    setSimilarLoading(true);
    try {
      console.log('Loading similar posts for:', postLinkName);
      const response = await apiClient.get(`/blog/${postLinkName}/similar`, {
        params: {
          limit: 5,
        }
      });
      const result = response.data;

      if (result.success && result.data?.posts) {
        setSimilarPosts(result.data.posts);
      } else {
        setSimilarPosts([]);
      }
    } catch (err: any) {
      console.error('Error fetching similar posts:', err);
      setSimilarPosts([]);
    } finally {
      setSimilarLoading(false);
    }
  }, []);

  // Извлечение заголовков для оглавления и обновление контента с ID
  const extractTableOfContents = useCallback((content: string) => {
    const parser = new DOMParser();
    const doc = parser.parseFromString(content, 'text/html');
    const headings = doc.querySelectorAll('h1, h2, h3, h4, h5, h6');
    const toc: TableOfContentsItem[] = [];

    headings.forEach((heading, index) => {
      const text = heading.textContent || '';
      const id = `heading-${index}-${text.toLowerCase().replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '')}`;
      heading.id = id;
      const level = parseInt(heading.tagName.charAt(1));
      toc.push({
        id,
        text,
        level,
      });
    });

    // Обновляем контекст оглавления
    setItems(toc);
    
    // Возвращаем обновленный контент с ID заголовков
    return doc.body.innerHTML;
  }, [setItems]);

  useEffect(() => {
    // Очищаем TOC перед загрузкой нового поста
    setItems([]);
    
    // Определяем linkName для использования
    // Если linkName не передан, извлекаем из slug
    let actualLinkName = linkName;
    if (!actualLinkName && slug && slug.length > 0) {
      const lastSegment = slug[slug.length - 1];
      if (lastSegment.startsWith('post-')) {
        actualLinkName = lastSegment.replace(/^post-/, '');
      }
    }
    
    if (!actualLinkName) {
      setError('Не удалось определить название поста');
      setLoading(false);
      return;
    }
    
    const fetchPost = async () => {
      try {
        // Используем API для получения поста по link_name
        // Ищем пост в списке постов блога
        // TODO: В будущем нужен отдельный endpoint для получения одного поста
        const response = await apiClient.get('/blog', {
          params: {
            limit: 1000, // Получаем достаточно постов для поиска
          }
        });
        const result = response.data;

        if (result.success && result.data?.posts) {
          // Ищем пост по linkName
          const post = result.data.posts.find((p: any) => p.linkName === actualLinkName);
          
          if (!post) {
            setError('Пост не найден');
            setLoading(false);
            return;
          }
          
          // Преобразуем данные поста в нужный формат
          const postData = {
            id: post.id,
            title: post.title,
            description: post.description,
            content: post.content,
            keywords: '',
            image: post.image || null,
            images: post.image ? [post.image] : [],
            category: post.category?.name || '',
            categoryLinkName: post.category?.linkName || '',
            parentCategoryName: null,
            parentCategoryLinkName: null,
            date: post.createdAt,
            views: post.views || 0,
            commentsCount: post.commentsCount || 0,
            link_name: post.linkName,
            url: post.url,
          };
          
          // Определяем link_name для загрузки похожих записей
          const postLinkName = post.linkName || actualLinkName;
          
          // Извлекаем заголовки и обновляем контент
          const updatedContent = extractTableOfContents(postData.content);
          setPost({ ...postData, content: updatedContent });
          
          // Загружаем похожие записи, если есть link_name
          if (postLinkName) {
            loadSimilarPosts(postLinkName);
          } else {
            console.warn('Cannot load similar posts: link_name is not available');
          }
        } else {
          setError('Пост не найден');
          // Очищаем TOC, если пост не найден
          setItems([]);
        }
      } catch (err: any) {
        console.error('Error fetching post:', err);
        setError(err.message || 'Ошибка при загрузке новости');
        // Очищаем TOC при ошибке
        setItems([]);
      } finally {
        setLoading(false);
      }
    };

    fetchPost();

    // Очищаем TOC при размонтировании компонента или изменении URL
    return () => {
      setItems([]);
    };
  }, [linkName, slug, extractTableOfContents, loadSimilarPosts, setItems]);

  // Очищаем TOC при изменении pathname (переход на другую страницу)
  useEffect(() => {
    // Проверяем, что мы на странице поста (URL содержит 'post-')
    const isPostPage = pathname && pathname.includes('post-');
    
    // Если ушли со страницы поста, очищаем TOC
    if (!isPostPage) {
      setItems([]);
    }
    
    return () => {
      // Очищаем при размонтировании
      setItems([]);
    };
  }, [pathname, setItems]);

  const formatDate = (dateString: string): string => {
    try {
      const date = new Date(dateString);
      return date.toLocaleDateString('ru-RU', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
    } catch {
      return dateString;
    }
  };


  // Формируем breadcrumbs на основе URL pathname
  const breadcrumbItems = useMemo(() => {
    if (!post || !pathname) return [];
    
    const items: any[] = [
      {
        href: '/',
        title: <HomeOutlined />,
      },
      {
        href: '/posts',
        title: (
          <>
            <FolderOutlined />
            <span>Блог</span>
          </>
        ),
      },
    ];

    // Парсим путь: /posts/category/subcategory/post-...
    const pathParts = pathname.split('/').filter(Boolean);
    
    if (pathParts.length > 1 && pathParts[0] === 'posts') {
      // Добавляем категорию если есть
      if (pathParts.length > 1 && !pathParts[1].startsWith('post-')) {
        items.push({
          href: `/posts/${pathParts[1]}`,
          title: (
            <>
              <FolderOutlined />
              <span>{post.parentCategoryName || post.parentCategoryLinkName || pathParts[1]}</span>
            </>
          ),
        });
      }
      
      // Добавляем подкатегорию если есть
      if (pathParts.length > 2 && !pathParts[2].startsWith('post-')) {
        items.push({
          href: `/posts/${pathParts[1]}/${pathParts[2]}`,
          title: (
            <>
              <FolderOutlined />
              <span>{post.category || pathParts[2]}</span>
            </>
          ),
        });
      }
    }

    // Добавляем название поста
    items.push({
      title: <span>{post.title}</span>,
    });

    return items;
  }, [post, pathname]);

  if (loading) {
    return (
      <div className="blog-post-page">
        <div className="container">
          <Skeleton
            active
            avatar={{ shape: 'square', size: 400 }}
            title={{ width: '80%' }}
            paragraph={{ rows: 8 }}
          />
        </div>
      </div>
    );
  }

  if (error || !post) {
    return (
      <div className="blog-post-page">
        <div className="container">
          <Result
            status="error"
            title={error || 'Новость не найдена'}
            subTitle="Попробуйте вернуться к списку новостей или обновить страницу"
            extra={[
              <Link key="back" href="/posts">
                <AntButton type="primary">Вернуться к списку новостей</AntButton>
              </Link>
            ]}
          />
        </div>
      </div>
    );
  }

  return (
    <div className="blog-post-page">
      <div className="container">
        {/* Основной контент */}
        <article className="blog-post__main">
          {/* Заголовок поста */}
          <div className="blog-post__header">
              <h1 className="blog-post__title">{post.title}</h1>

              {/* Хлебные крошки под заголовком */}
              <Breadcrumb
                className="blog-post__breadcrumbs"
                items={breadcrumbItems}
                itemRender={(route, params, items, paths) => {
                  const isLast = items.indexOf(route) === items.length - 1;
                  return isLast ? (
                    <span>{route.title}</span>
                  ) : (
                    <Link href={route.href || '#'}>{route.title}</Link>
                  );
                }}
              />

              <div className="blog-post__meta">
                <div className="blog-post__meta-item">
                  <CalendarToday className="blog-post__meta-icon" />
                  <time>{formatDate(post.date)}</time>
                </div>
                
                <div className="blog-post__meta-item">
                  <Visibility className="blog-post__meta-icon" />
                  <span>{post.views.toLocaleString('ru-RU')}</span>
                </div>

                {post.category && (
                  <div className="blog-post__meta-item">
                    <Tag color="blue" icon={<Label />}>
                      {post.category}
                    </Tag>
                  </div>
                )}
              </div>

              {/* Ключевые слова */}
              {post.keywords && (
                <div className="blog-post__keywords">
                  {post.keywords.split(',').map((keyword, index) => (
                    <Tag key={index} bordered>
                      {keyword.trim()}
                    </Tag>
                  ))}
                </div>
              )}
            </div>

          {/* Контент поста */}
          <div
            className="blog-post__content tinymce-content"
            dangerouslySetInnerHTML={{ __html: post.content }}
          />

          {/* Галерея изображений (если есть дополнительные) */}
          {post.images && post.images.length > 1 && (
            <div className="blog-post__gallery">
              {post.images.slice(1).map((image, index) => (
                <div key={index} className="blog-post__gallery-item">
                  <img
                    src={image}
                    alt={`${post.title} - изображение ${index + 2}`}
                  />
                </div>
              ))}
            </div>
          )}

          {/* Футер поста */}
          <div className="blog-post__footer">
            <div className="blog-post__stats">
              <div className="blog-post__stat">
                <Visibility className="blog-post__stat-icon" />
                <span>{post.views.toLocaleString('ru-RU')} просмотров</span>
              </div>
              <div className="blog-post__stat">
                <Comment className="blog-post__stat-icon" />
                <span>{post.commentsCount} комментариев</span>
              </div>
            </div>

            {/* Социальные кнопки */}
            <div className="blog-post__share">
              <span className="blog-post__share-label">Поделиться:</span>
              <div className="blog-post__share-buttons">
                <FacebookShareButton
                  url={typeof window !== 'undefined' ? window.location.href : post.url}
                  title={post.title}
                  className="blog-post__share-button"
                >
                  <FacebookIcon size={32} round />
                </FacebookShareButton>
                <TwitterShareButton
                  url={typeof window !== 'undefined' ? window.location.href : post.url}
                  title={post.title}
                  className="blog-post__share-button"
                >
                  <TwitterIcon size={32} round />
                </TwitterShareButton>
                <VKShareButton
                  url={typeof window !== 'undefined' ? window.location.href : post.url}
                  title={post.title}
                  className="blog-post__share-button"
                >
                  <VKIcon size={32} round />
                </VKShareButton>
                <TelegramShareButton
                  url={typeof window !== 'undefined' ? window.location.href : post.url}
                  title={post.title}
                  className="blog-post__share-button"
                >
                  <TelegramIcon size={32} round />
                </TelegramShareButton>
                <WhatsappShareButton
                  url={typeof window !== 'undefined' ? window.location.href : post.url}
                  title={post.title}
                  className="blog-post__share-button"
                >
                  <WhatsappIcon size={32} round />
                </WhatsappShareButton>
              </div>
            </div>
          </div>
        </article>

        {/* Похожие записи */}
        {similarLoading ? (
          <section className="blog-post__similar">
            <h2 className="blog-post__similar-title">Похожие записи</h2>
            <Spin size="large" />
          </section>
        ) : similarPosts.length > 0 ? (
          <section className="blog-post__similar">
            <h2 className="blog-post__similar-title">Похожие записи</h2>
            <div className="blog-post__similar-list">
              {similarPosts.map((similarPost) => (
                <BlogMiniCard
                  key={similarPost.id}
                  id={similarPost.id}
                  title={similarPost.title}
                  description={similarPost.description}
                  image={similarPost.image}
                  category={typeof similarPost.category === 'string' ? similarPost.category : (similarPost.category?.name || undefined)}
                  date={similarPost.createdAt || similarPost.date || ''}
                  url={similarPost.url}
                />
              ))}
            </div>
          </section>
        ) : null}

        {/* Комментарии */}
        {post && (
          <BlogComments blogId={post.linkName || post.link_name || String(post.id)} />
        )}
      </div>
    </div>
  );
}

