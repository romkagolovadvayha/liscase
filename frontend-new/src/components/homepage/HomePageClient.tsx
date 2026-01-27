'use client';

import React, { useState, useMemo, useEffect, useRef, useCallback } from 'react';
import UserStats from '@/components/homepage/UserStats';
import DailyReward from '@/components/homepage/DailyReward';
import Search from '@/components/homepage/Search';
import Categories from '@/components/homepage/Categories';
import ProductCard from '@/components/homepage/ProductCard';
import ProductModal from '@/components/products/ProductModal';
import { useProducts, type Product } from '@/hooks/useProducts';
import { useProductCategories, type Category } from '@/hooks/useProductCategories';
import { isAuthenticated } from '@/lib/api/auth';
import { useHomepageData } from '@/providers/HomepageDataProvider';
import { useSettings } from '@/hooks/useSettings';
import { getStatsImage, getStatsImageVideo, getBonusImage, getBonusImageVideo, getNotAuthImage } from '@/lib/utils/settingsImage';
import '@/styles/homepage.scss';

interface HomePageData {
  categories: Category[];
  products: Product[];
  projectStats: {
    online: number;
    users: number;
    count: number;
  };
  userData?: {
    username: string;
    userStats?: Record<string, number>;
    awards?: Array<{ id: number; name: string; image: string; completed: boolean }>;
    awardsStats?: { completed: number; total: number };
    userStatsLink?: string;
    serverActiveTag?: string;
  } | null;
  serverActiveTag?: string | null;
  settings?: {
    botLink?: string;
    bonusImage?: string;
    bonusImageVideo?: string;
    statsImage?: string;
    statsImageVideo?: string;
    notAuthImage?: string;
  };
}

interface HomePageClientProps {
  initialData?: HomePageData;
}

const PRODUCTS_PER_PAGE = 20;

export default function HomePageClient({ initialData }: HomePageClientProps = {}) {
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [projectStats, setProjectStats] = useState(initialData?.projectStats || { online: 0, users: 0, count: 0 });
  
  // Используем глобальный провайдер для homepage данных
  const { userData: globalUserData, isLoading: isLoadingHomepageData } = useHomepageData();
  const [userData, setUserData] = useState(initialData?.userData || globalUserData || null);
  
  const [isUserAuthenticated, setIsUserAuthenticated] = useState(() => {
    // Инициализируем состояние авторизации при монтировании
    if (typeof window !== 'undefined') {
      return isAuthenticated();
    }
    return !!initialData?.userData;
  });
  
  // Получаем настройки из API
  const { data: apiSettings } = useSettings();
  const cdnUrl = apiSettings?.site?.cdnUrl as string | null | undefined;
  
  // Получаем изображения из настроек API
  const statsImage = getStatsImage(apiSettings, cdnUrl);
  const statsImageVideo = getStatsImageVideo(apiSettings, cdnUrl);
  const bonusImage = getBonusImage(apiSettings, cdnUrl);
  const bonusImageVideo = getBonusImageVideo(apiSettings, cdnUrl);
  const notAuthImage = getNotAuthImage(apiSettings, cdnUrl);
  
  const [settings, setSettings] = useState(initialData?.settings || {
    botLink: '#',
    bonusImage: '',
    bonusImageVideo: '',
    statsImage: '',
    statsImageVideo: '',
    notAuthImage: '',
  });
  
  // Обновляем настройки из API при их загрузке
  useEffect(() => {
    if (apiSettings) {
      setSettings(prev => ({
        ...prev,
        bonusImage,
        bonusImageVideo,
        statsImage,
        statsImageVideo,
        notAuthImage,
      }));
    }
  }, [apiSettings, bonusImage, bonusImageVideo, statsImage, statsImageVideo, notAuthImage]);
  const observerTarget = useRef<HTMLDivElement>(null);
  const [isMounted, setIsMounted] = useState(false);

  // Отслеживаем монтирование компонента (только на клиенте)
  useEffect(() => {
    setIsMounted(true);
  }, []);

  // Отслеживаем изменения авторизации (например, после сохранения токенов в localStorage)
  useEffect(() => {
    // Проверяем авторизацию при монтировании
    const authenticated = isAuthenticated();
    if (authenticated !== isUserAuthenticated) {
      setIsUserAuthenticated(authenticated);
    }

    // Слушаем изменения в localStorage (для случая, если токены сохраняются на этой же странице)
    const handleStorageChange = (e: StorageEvent) => {
      if (e.key === 'access_token') {
        const newAuthState = isAuthenticated();
        setIsUserAuthenticated(newAuthState);
      }
    };

    window.addEventListener('storage', handleStorageChange);
    
    return () => {
      window.removeEventListener('storage', handleStorageChange);
    };
  }, [isUserAuthenticated]);

  // Обновляем userData из глобального провайдера
  useEffect(() => {
    if (globalUserData) {
      setUserData(globalUserData);
    } else if (!isLoadingHomepageData && !isUserAuthenticated) {
      // Если данные загружены и пользователь не авторизован, очищаем userData
      setUserData(null);
    }
  }, [globalUserData, isLoadingHomepageData, isUserAuthenticated]);

  // Используем React Query для категорий (предотвращает дублирование запросов)
  const { data: categoriesData, isLoading: categoriesLoading } = useProductCategories(1);
  const categories = categoriesData?.data || [];

      // Всегда создаем список с категорией "Все" в начале
      const categoriesWithAll = useMemo(() => {
        const allCategory = { id: 0, name: 'Все', image: undefined };
        const otherCategories = categories.map((cat) => ({
          id: cat.id,
          name: cat.name,
          image: cat.image || undefined,
        }));
        return [allCategory, ...otherCategories];
      }, [categories]);

  // Используем React Query для продуктов с бесконечной прокруткой (предотвращает дублирование запросов)
  const { 
    data: productsData, 
    isLoading: isLoading, 
    fetchNextPage, 
    hasNextPage,
    isFetchingNextPage 
  } = useProducts({
    limit: PRODUCTS_PER_PAGE,
    categoryId: selectedCategory,
    search: searchQuery,
  });

  // Объединяем все страницы продуктов в один массив
  const products = useMemo(() => {
    return productsData?.pages.flatMap(page => page.data || []) || [];
  }, [productsData]);

  const hasMore = hasNextPage ?? false;

  // Загрузка следующей страницы товаров
  const loadMoreProducts = useCallback(() => {
    if (isFetchingNextPage || !hasMore) return;
    fetchNextPage();
  }, [isFetchingNextPage, hasMore, fetchNextPage]);

  // Intersection Observer для бесконечной прокрутки
  useEffect(() => {
    if (!hasMore || isFetchingNextPage) return;

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting) {
          loadMoreProducts();
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
  }, [hasMore, isFetchingNextPage, loadMoreProducts]);

  // Определяем isGuest: если userData есть, используем его, иначе проверяем состояние авторизации
  // На сервере всегда показываем гостевую версию, чтобы избежать hydration ошибок
  const isGuest = useMemo(() => {
    if (!isMounted) {
      return true; // На сервере и до монтирования показываем гостевую версию
    }
    if (userData) {
      return false; // Если есть userData, пользователь авторизован
    }
    // Если userData отсутствует, используем состояние авторизации
    return !isUserAuthenticated;
  }, [userData, isUserAuthenticated, isMounted]);

  // Формируем данные для ProductCard
  const formatProduct = (product: Product) => {
    // Преобразуем цены в числа, если они строки
    const productPrice = typeof product.price === 'string' ? parseFloat(product.price) : (product.price || 0);
    const productPriceReal = typeof product.priceReal === 'string' 
      ? parseFloat(product.priceReal) 
      : (product.priceReal ?? productPrice);
    
    // Используем priceReal из продукта, если есть, иначе вычисляем
    const priceReal = productPriceReal;
    const price = product.discount && product.discount > 0
      ? Math.round(priceReal * (1 + product.discount / 100))
      : productPrice;

    // Используем изображение только если оно есть (не пустая строка)
    // Если изображение отсутствует, используем заглушку
    const productImage = product.image && product.image.trim() !== '' 
      ? product.image 
      : '/images/placeholder.png';

    return {
      id: product.id,
      name: product.name,
      image: productImage,
      price: price !== priceReal ? price : 0,
      priceReal: priceReal,
      discount: product.discount || undefined,
      count: product.count,
    };
  };

  return (
    <div className="homepage">
      {/* Блок статистики и ежедневная награда */}
      <div className="info">
        <UserStats
          isGuest={isGuest}
          username={userData?.username}
          projectStats={projectStats}
          userStats={userData?.userStats}
          awards={userData?.awards}
          awardsStats={userData?.awardsStats}
          userStatsLink={userData?.userStatsLink || undefined}
          serverActiveTag={userData?.serverActiveTag || undefined}
          statsImage={statsImage}
          statsImageVideo={statsImageVideo}
          notAuthImage={notAuthImage}
        />
        <DailyReward
          botLink={settings?.botLink}
          bonusImage={bonusImage}
          bonusImageVideo={bonusImageVideo}
        />
      </div>

      {/* Поиск */}
      <div className="homepage-search">
        <Search onSearch={setSearchQuery} />
      </div>

      {/* Категории */}
      <div className="homepage-categories">
        <Categories
          categories={categoriesWithAll}
          activeCategoryId={selectedCategory}
          onCategoryClick={setSelectedCategory}
          isLoading={categoriesLoading}
        />
      </div>

      {/* Товары */}
      <div className="homepage-products">
        <div className="products-grid">
          {products.length > 0 ? (
            <>
              {products.map((product) => {
                const formatted = formatProduct(product);
                return (
                  <ProductCard
                    key={product.id}
                    id={formatted.id}
                    name={formatted.name}
                    image={formatted.image}
                    price={formatted.price}
                    priceReal={formatted.priceReal}
                    discount={formatted.discount}
                    count={formatted.count}
                    onClick={(id) => {
                      const selectedProduct = products.find(p => p.id === id);
                      setSelectedProduct(selectedProduct || null);
                      setIsModalOpen(true);
                    }}
                  />
                );
              })}
              {/* Элемент для отслеживания скролла */}
              {hasMore && (
                <div ref={observerTarget} style={{ gridColumn: '1 / -1', minHeight: '20px' }}>
                  {isFetchingNextPage && (
                    <div style={{ textAlign: 'center', padding: '20px' }}>
                      <p>Загрузка...</p>
                    </div>
                  )}
                </div>
              )}
            </>
          ) : isLoading ? (
            // Skeleton для товаров
            <>
              {[1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12].map((i) => (
                <div key={i} style={{ 
                  aspectRatio: '1 / 1',
                  backgroundColor: 'var(--background-hover)', 
                  borderRadius: 'var(--card-radius)',
                  padding: '12px',
                  display: 'flex',
                  flexDirection: 'column',
                }}>
                  <div style={{ 
                    width: '100%', 
                    height: '60%', 
                    backgroundColor: 'rgba(255,255,255,0.1)', 
                    borderRadius: 8, 
                    marginBottom: 12 
                  }}></div>
                  <div style={{ height: 16, backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: 4, marginBottom: 8, width: '80%' }}></div>
                  <div style={{ height: 14, backgroundColor: 'rgba(255,255,255,0.1)', borderRadius: 4, width: '50%' }}></div>
                </div>
              ))}
            </>
          ) : (
            <div style={{ gridColumn: '1 / -1', textAlign: 'center', padding: '40px' }}>
              <p>Товары не найдены</p>
            </div>
          )}
        </div>
      </div>

      <ProductModal
        product={selectedProduct}
        isOpen={isModalOpen}
        onClose={() => {
          setIsModalOpen(false);
          setSelectedProduct(null);
        }}
        onPurchaseSuccess={(newBalance) => {
          // Обновление баланса будет происходить через WebSocket
          // Здесь можно добавить дополнительную логику
        }}
        isGuest={isGuest}
      />
    </div>
  );
}
