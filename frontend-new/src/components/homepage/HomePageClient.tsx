'use client';

import React, { useState, useMemo, useEffect, useRef, useCallback } from 'react';
import UserStats from '@/components/homepage/UserStats';
import DailyReward from '@/components/homepage/DailyReward';
import Search from '@/components/homepage/Search';
import Categories from '@/components/homepage/Categories';
import ProductCard from '@/components/homepage/ProductCard';
import ProductModal from '@/components/products/ProductModal';
import '@/styles/homepage.scss';

interface Category {
  id: number;
  name: string;
  image?: string;
}

interface SubDrop {
  id: number;
  drop_id: number;
  count: number;
  name: string;
  price: number;
  image?: string;
}

interface Product {
  id: number;
  name: string;
  image?: string;
  price: number;
  priceReal?: number;
  count?: number;
  discount?: number;
  category_id: number;
  description?: string;
  drop_type?: number;
  subDrops?: SubDrop[];
  floating_price_percent?: number;
}

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
  initialData: HomePageData;
}

const PRODUCTS_PER_PAGE = 20;

export default function HomePageClient({ initialData }: HomePageClientProps) {
  const [selectedCategory, setSelectedCategory] = useState<number | null>(null);
  const [searchQuery, setSearchQuery] = useState('');
  const [products, setProducts] = useState<Product[]>(initialData.products);
  const [isLoading, setIsLoading] = useState(false);
  const [hasMore, setHasMore] = useState(initialData.products.length >= PRODUCTS_PER_PAGE);
  const [offset, setOffset] = useState(initialData.products.length);
  const [selectedProduct, setSelectedProduct] = useState<Product | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const observerTarget = useRef<HTMLDivElement>(null);

  // Добавляем категорию "Все" в начало списка
  const categoriesWithAll = useMemo(() => {
    return [
      { id: 0, name: 'Все', image: undefined },
      ...initialData.categories.map((cat) => ({
        id: cat.id,
        name: cat.name,
        image: cat.image || undefined,
      })),
    ];
  }, [initialData.categories]);

  // Загрузка товаров через API
  const loadMoreProducts = useCallback(async () => {
    if (isLoading || !hasMore) return;

    setIsLoading(true);
    try {
      const params = new URLSearchParams({
        limit: PRODUCTS_PER_PAGE.toString(),
        offset: offset.toString(),
      });

      if (selectedCategory !== null && selectedCategory !== 0) {
        params.append('category_id', selectedCategory.toString());
      }

      if (searchQuery) {
        params.append('search', searchQuery);
      }

      const response = await fetch(`/api/products?${params.toString()}`);
      const result = await response.json();

      if (result.success && result.data) {
        const newProducts = result.data as Product[];
        setProducts((prev) => [...prev, ...newProducts]);
        setOffset((prev) => prev + newProducts.length);
        setHasMore(newProducts.length === PRODUCTS_PER_PAGE);
      } else {
        setHasMore(false);
      }
    } catch (error) {
      console.error('Error loading products:', error);
      setHasMore(false);
    } finally {
      setIsLoading(false);
    }
  }, [isLoading, hasMore, offset, selectedCategory, searchQuery]);

  // Сброс и загрузка товаров при изменении фильтров
  useEffect(() => {
    const loadFilteredProducts = async () => {
      setIsLoading(true);
      setProducts([]);
      setOffset(0);
      setHasMore(true);

      try {
        const params = new URLSearchParams({
          limit: PRODUCTS_PER_PAGE.toString(),
          offset: '0',
        });

        if (selectedCategory !== null && selectedCategory !== 0) {
          params.append('category_id', selectedCategory.toString());
        }

        if (searchQuery) {
          params.append('search', searchQuery);
        }

        const response = await fetch(`/api/products?${params.toString()}`);
        const result = await response.json();

        if (result.success && result.data) {
          const newProducts = result.data as Product[];
          setProducts(newProducts);
          setOffset(newProducts.length);
          setHasMore(newProducts.length === PRODUCTS_PER_PAGE);
        } else {
          setHasMore(false);
        }
      } catch (error) {
        console.error('Error loading filtered products:', error);
        setHasMore(false);
      } finally {
        setIsLoading(false);
      }
    };

    loadFilteredProducts();
  }, [selectedCategory, searchQuery]);

  // Intersection Observer для бесконечной прокрутки
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && hasMore && !isLoading) {
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
  }, [hasMore, isLoading, loadMoreProducts]);

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
                isGuest={!initialData.userData}
                username={initialData.userData?.username}
                projectStats={initialData.projectStats}
                userStats={initialData.userData?.userStats}
                awards={initialData.userData?.awards}
                awardsStats={initialData.userData?.awardsStats}
                userStatsLink={initialData.userData?.userStatsLink}
                serverActiveTag={initialData.userData?.serverActiveTag || initialData.serverActiveTag || undefined}
                statsImage={initialData.settings?.statsImage}
                statsImageVideo={initialData.settings?.statsImageVideo}
                notAuthImage={initialData.settings?.notAuthImage}
              />
        <DailyReward
          botLink={initialData.settings?.botLink}
          bonusImage={initialData.settings?.bonusImage}
          bonusImageVideo={initialData.settings?.bonusImageVideo}
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
                  {isLoading && (
                    <div style={{ textAlign: 'center', padding: '20px' }}>
                      <p>Загрузка...</p>
                    </div>
                  )}
                </div>
              )}
            </>
          ) : (
            <div style={{ gridColumn: '1 / -1', textAlign: 'center', padding: '40px' }}>
              {isLoading ? <p>Загрузка...</p> : <p>Товары не найдены</p>}
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
        isGuest={!initialData.userData}
      />
    </div>
  );
}

