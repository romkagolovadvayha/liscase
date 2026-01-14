'use client';

import React, { useState, useEffect, useRef, useCallback, useMemo } from 'react';
import { Masonry, Breadcrumb, Skeleton, Spin, Result } from 'antd';
import { HomeOutlined, FolderOutlined } from '@ant-design/icons';
import { useRouter, useSearchParams, usePathname } from 'next/navigation';
import Link from 'next/link';
import BlogCard from '@/components/homepage/BlogCard';
import Input from '@/components/forms/Input';
import Button from '@/components/forms/Button';
import Tabs from '@/components/design-system/Tabs';
import apiClient from '@/lib/api/client';
import '@/styles/blog.scss';

interface BlogPost {
  id: number;
  title: string;
  description: string;
  image?: string;
  category?: {
    id: number;
    name: string;
    linkName: string;
  } | null;
  date: string;
  createdAt?: string;
  views: number;
  url: string;
}

interface BlogData {
  posts: BlogPost[];
  pagination: {
    page: number;
    limit: number;
    total: number;
    totalPages: number;
  };
}

interface Category {
  id: number;
  name: string;
  linkName: string;
  url: string;
  children?: Category[];
}

const POSTS_PER_PAGE = 10;

export default function BlogClient() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const pathname = usePathname();
  
  const [posts, setPosts] = useState<BlogPost[]>([]);
  const [loading, setLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const observerTarget = useRef<HTMLDivElement>(null);
  
  // Фильтры
  const [search, setSearch] = useState(searchParams.get('search') || '');
  const [sort, setSort] = useState(searchParams.get('sort') || 'created_at');
  const [order, setOrder] = useState<'asc' | 'desc'>((searchParams.get('order') as 'asc' | 'desc') || 'desc');
  
  // Парсим категории из пути URL
  // /posts - все категории
  // /posts/rust-news - категория
  // /posts/rust-news/kick-drops - подкатегория
  const pathParts = pathname.split('/').filter(Boolean);
  const categoryLinkName = pathParts.length > 1 && pathParts[0] === 'posts' ? pathParts[1] : null;
  const subcategoryLinkName = pathParts.length > 2 && pathParts[0] === 'posts' ? pathParts[2] : null;
  
  const [selectedCategoryId, setSelectedCategoryId] = useState<number | null>(null);
  const [categories, setCategories] = useState<Category[]>([]);
  const [categoriesLoading, setCategoriesLoading] = useState(true);

  // Загрузка постов
  const loadPosts = useCallback(async (page: number, append: boolean = false) => {
    if (append && (isLoadingMore || !hasMore)) return;

    if (append) {
      setIsLoadingMore(true);
    } else {
      setLoading(true);
    }
    setError(null);
    
    try {
      const params = new URLSearchParams({
        page: page.toString(),
        limit: POSTS_PER_PAGE.toString(),
        sort,
        order,
      });
      
      if (search) {
        params.append('search', search);
      }

      if (selectedCategoryId) {
        params.append('category_id', selectedCategoryId.toString());
      }

      const response = await apiClient.get(`/blog?${params.toString()}`);
      const result = response.data;
      
      if (result.success) {
        // Преобразуем данные постов: используем createdAt как date, если date не задано
        const postsData = result.data.posts.map((post: any) => ({
          ...post,
          date: post.date || post.createdAt || '',
        }));
        
        if (append) {
          setPosts((prev) => [...prev, ...postsData]);
        } else {
          setPosts(postsData);
        }
        setCurrentPage(page);
        setTotalPages(result.data.pagination.totalPages);
        setHasMore(page < result.data.pagination.totalPages);
      } else {
        setError(result.message || 'Ошибка при загрузке новостей');
        setHasMore(false);
      }
    } catch (err: any) {
      console.error('Error fetching posts:', err);
      setError(err.message || 'Ошибка при загрузке новостей');
      setHasMore(false);
    } finally {
      if (append) {
        setIsLoadingMore(false);
      } else {
        setLoading(false);
      }
    }
  }, [search, sort, order, selectedCategoryId, hasMore, isLoadingMore]);

  // Загрузка следующей страницы
  const loadMorePosts = useCallback(() => {
    if (!isLoadingMore && hasMore) {
      loadPosts(currentPage + 1, true);
    }
  }, [currentPage, hasMore, isLoadingMore, loadPosts]);

  // Обновление URL при изменении категории
  const updateCategoryURL = useCallback((categoryLink: string | null, subcategoryLink: string | null) => {
    let newPath = '/posts';
    
    if (categoryLink) {
      newPath = `/posts/${categoryLink}`;
      if (subcategoryLink) {
        newPath = `/posts/${categoryLink}/${subcategoryLink}`;
      }
    }

    // Сохраняем query параметры (search, sort, order)
    const newParams = new URLSearchParams(searchParams.toString());
    
    // Удаляем значения по умолчанию
    if (newParams.get('sort') === 'created_at') newParams.delete('sort');
    if (newParams.get('order') === 'desc') newParams.delete('order');
    if (!newParams.get('search')) newParams.delete('search');

    const queryString = newParams.toString();
    router.push(`${newPath}${queryString ? `?${queryString}` : ''}`, { scroll: false });
  }, [router, searchParams]);

  // Загрузка категорий и определение выбранной категории из URL
  useEffect(() => {
    const fetchCategories = async () => {
      try {
        // TODO: blog/categories endpoint пока не реализован в новом API
        const response = await apiClient.get('/blog/categories');
        const result = response.data;
        
        if (result.success) {
          setCategories(result.data);
          
          // Определяем выбранную категорию из URL
          if (subcategoryLinkName) {
            // Ищем подкатегорию
            for (const category of result.data) {
              const subcategory = category.children?.find((c: Category) => c.linkName === subcategoryLinkName);
              if (subcategory) {
                setSelectedCategoryId(subcategory.id);
                break;
              }
            }
          } else if (categoryLinkName) {
            // Ищем родительскую категорию
            const category = result.data.find((c: Category) => c.linkName === categoryLinkName);
            if (category) {
              setSelectedCategoryId(category.id);
            }
          } else {
            setSelectedCategoryId(null);
          }
        }
      } catch (err) {
        console.error('Error fetching categories:', err);
      } finally {
        setCategoriesLoading(false);
      }
    };

    fetchCategories();
  }, [categoryLinkName, subcategoryLinkName]);

  // Сброс и загрузка при изменении фильтров
  useEffect(() => {
    setPosts([]);
    setCurrentPage(0);
    setHasMore(true);
    loadPosts(1, false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search, sort, order, selectedCategoryId]);

  // Intersection Observer для бесконечной прокрутки
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && hasMore && !isLoadingMore && !loading) {
          loadMorePosts();
        }
      },
      { threshold: 0.1 }
    );

    const currentTarget = observerTarget.current;
    if (currentTarget) {
      observer.observe(currentTarget);
    }

    return () => {
      if (currentTarget) {
        observer.unobserve(currentTarget);
      }
    };
  }, [hasMore, isLoadingMore, loading, loadMorePosts]);

  const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = e.target.value;
    setSearch(value);
    // URL не меняется при поиске
  };

  const handleSortChange = (newSort: string) => {
    if (sort === newSort) {
      setOrder(order === 'asc' ? 'desc' : 'asc');
    } else {
      setSort(newSort);
      setOrder('desc');
    }
    // URL не меняется при сортировке
  };

  // Обработка выбора категории
  const handleCategoryChange = useCallback((categoryId: string) => {
    if (categoryId === 'all') {
      setSelectedCategoryId(null);
      updateCategoryURL(null, null);
      return;
    }

    // Ищем категорию
    let foundCategory: Category | null = null;
    let foundSubcategory: Category | null = null;

    for (const category of categories) {
      if (category.id.toString() === categoryId) {
        foundCategory = category;
        break;
      }
      const subcategory = category.children?.find(c => c.id.toString() === categoryId);
      if (subcategory) {
        foundCategory = category;
        foundSubcategory = subcategory;
        break;
      }
    }

    if (foundCategory) {
      setSelectedCategoryId(foundSubcategory ? foundSubcategory.id : foundCategory.id);
      updateCategoryURL(
        foundCategory.linkName,
        foundSubcategory ? foundSubcategory.linkName : null
      );
    }
  }, [categories, updateCategoryURL]);

  // Формируем breadcrumbs на основе URL pathname
  const breadcrumbItems = useMemo(() => {
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

    // Парсим путь: /posts/category/subcategory
    const pathParts = pathname.split('/').filter(Boolean);
    
    if (pathParts.length > 1 && pathParts[0] === 'posts') {
      // Добавляем категорию если есть (pathParts[1] - это категория)
      if (pathParts.length >= 2 && pathParts[1] && !pathParts[1].startsWith('post-')) {
        const category = categories.find(cat => cat.linkName === pathParts[1]);
        if (category || pathParts[1]) {
          items.push({
            href: `/posts/${pathParts[1]}`,
            title: (
              <>
                <FolderOutlined />
                <span>{category?.name || pathParts[1]}</span>
              </>
            ),
          });
        }
      }
      
      // Добавляем подкатегорию если есть (pathParts[2] - это подкатегория)
      if (pathParts.length >= 3 && pathParts[2] && !pathParts[2].startsWith('post-')) {
        const parentCategory = categories.find(cat => cat.linkName === pathParts[1]);
        const subcategory = parentCategory?.children?.find(child => child.linkName === pathParts[2]);
        if (subcategory || pathParts[2]) {
          items.push({
            href: `/posts/${pathParts[1]}/${pathParts[2]}`,
            title: (
              <>
                <FolderOutlined />
                <span>{subcategory?.name || pathParts[2]}</span>
              </>
            ),
          });
        }
      }
    }

    return items;
  }, [pathname, categories]);

  return (
    <div className="blog-page">
      <div className="container">
        {/* Заголовок */}
        <div className="blog-page__header">
          <h1 className="page-title">Блог</h1>
          <p className="page-description">
            Новости, баги, обновления и полезные статьи
          </p>
          
          {/* Хлебные крошки под описанием */}
          <Breadcrumb
            className="blog-page__breadcrumbs"
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
        </div>

        {/* Поиск и фильтры */}
        <div className="blog-page__search-filters">
          <Input
            type="text"
            placeholder="Поиск по названию..."
            value={search}
            onChange={handleSearch}
            style={{ maxWidth: '300px' }}
          />
          <div className="blog-page__sort-filters">
            <Button
              onClick={() => handleSortChange('created_at')}
              variant={sort === 'created_at' ? 'primary' : 'secondary'}
              size="small"
              rightIcon={sort === 'created_at' ? (order === 'asc' ? 'arrow-up' : 'arrow-down') : undefined}
              iconSize="small"
            >
              По дате
            </Button>
            <Button
              onClick={() => handleSortChange('views')}
              variant={sort === 'views' ? 'primary' : 'secondary'}
              size="small"
              rightIcon={sort === 'views' ? (order === 'asc' ? 'arrow-up' : 'arrow-down') : undefined}
              iconSize="small"
            >
              По просмотрам
            </Button>
          </div>
        </div>

        {/* Выбор категорий через Tabs */}
        {!categoriesLoading && categories.length > 0 && (
          <div className="blog-page__categories">
            <Tabs
              tabs={[
                { id: 'all', label: 'Все' },
                ...categories.map((category) => ({
                  id: category.id.toString(),
                  label: category.name,
                })),
              ]}
              activeTab={
                (() => {
                  // Если выбрана подкатегория, находим её родительскую категорию
                  if (selectedCategoryId) {
                    for (const category of categories) {
                      if (category.id === selectedCategoryId) {
                        return category.id.toString();
                      }
                      if (category.children?.some(c => c.id === selectedCategoryId)) {
                        return category.id.toString();
                      }
                    }
                  }
                  return 'all';
                })()
              }
              onChange={handleCategoryChange}
            />
            
            {/* Подкатегории под выбранной родительской категорией */}
            {(() => {
              // Находим выбранную родительскую категорию
              const selectedParentCategory = categories.find(
                (category) => category.id === selectedCategoryId || 
                category.children?.some(c => c.id === selectedCategoryId)
              );

              if (!selectedParentCategory || !selectedParentCategory.children || selectedParentCategory.children.length === 0) {
                return null;
              }

              return (
                <div className="blog-page__subcategories">
                  <Tabs
                    tabs={[
                      {
                        id: selectedParentCategory.id.toString(),
                        label: 'Все подкатегории',
                      },
                      ...selectedParentCategory.children.map((child) => ({
                        id: child.id.toString(),
                        label: child.name,
                      })),
                    ]}
                    activeTab={
                      selectedCategoryId === selectedParentCategory.id
                        ? selectedParentCategory.id.toString()
                        : selectedCategoryId?.toString() || selectedParentCategory.id.toString()
                    }
                    onChange={handleCategoryChange}
                    className="blog-page__subcategories-tabs"
                  />
                </div>
              );
            })()}
          </div>
        )}

        {/* Список постов */}
        {loading && posts.length === 0 ? (
          <div className="blog-page__skeleton">
            <Skeleton
              active
              avatar={{ shape: 'square' }}
              title
              paragraph={{ rows: 3 }}
            />
            <Skeleton
              active
              avatar={{ shape: 'square' }}
              title
              paragraph={{ rows: 3 }}
            />
            <Skeleton
              active
              avatar={{ shape: 'square' }}
              title
              paragraph={{ rows: 3 }}
            />
          </div>
        ) : error ? (
          <Result
            status="error"
            title="Ошибка загрузки"
            subTitle={error}
            extra={[
              <Button
                key="retry"
                onClick={() => {
                  setError(null);
                  setCurrentPage(1);
                  setPosts([]);
                  setHasMore(true);
                  loadPosts(1, false);
                }}
              >
                Попробовать снова
              </Button>
            ]}
          />
        ) : posts.length === 0 ? (
          <Result
            status="404"
            title="Новости не найдены"
            subTitle="Попробуйте изменить параметры поиска или выбрать другую категорию"
          />
        ) : (
          <>
            <Masonry
              items={posts.map((post) => ({
                key: post.id,
                children: (
                  <BlogCard
                    id={post.id}
                    title={post.title}
                    description={post.description}
                    image={post.image}
                    category={post.category?.name || undefined}
                    date={post.date}
                    url={post.url}
                  />
                ),
                data: post,
              }))}
              columns={{ xs: 1, sm: 2, md: 3, lg: 3, xl: 4 }}
              gutter={[24, 24]}
              fresh={true}
            />

            {/* Элемент для отслеживания скролла */}
            {hasMore && (
              <div ref={observerTarget} style={{ minHeight: '20px', width: '100%', marginTop: '24px' }}>
                {isLoadingMore && (
                  <div style={{ textAlign: 'center', padding: '20px' }}>
                    <Spin size="large" />
                  </div>
                )}
              </div>
            )}
          </>
        )}
      </div>
    </div>
  );
}

