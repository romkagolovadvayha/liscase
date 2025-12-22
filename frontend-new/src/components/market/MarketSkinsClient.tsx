'use client';

import React, { useState, useEffect, useRef, useCallback } from 'react';
import Icon from '@/components/icons/Icon';
import Button from '@/components/forms/Button';
import Input from '@/components/forms/Input';
import Tabs from '@/components/design-system/Tabs';
import ProductCard from '@/components/homepage/ProductCard';
import ProductModal from '@/components/products/ProductModal';
import '@/styles/market-skins.scss';

interface MarketSkin {
  id: number;
  class_id: string;
  instance_id: string;
  game_type: 'rust' | 'cs2';
  market_hash_name: string;
  name: string;
  ru_name: string | null;
  category: string | null;
  ru_quality: string | null;
  text_color: string | null;
  bg_color: string | null;
  price: number;
  our_price: number;
  markup_percent: number;
  avg_price: number | null;
  popularity_7d: number;
  image_url: string;
  image300_url: string;
  is_stat_trak: boolean;
}

interface MarketSkinsData {
  items: MarketSkin[];
  pagination: {
    page: number;
    limit: number;
    total: number;
    totalPages: number;
  };
  categories: string[];
}

const SKINS_PER_PAGE = 24;

export default function MarketSkinsClient() {
  const [skins, setSkins] = useState<MarketSkin[]>([]);
  const [loading, setLoading] = useState(true);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(true);
  const [currentPage, setCurrentPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const observerTarget = useRef<HTMLDivElement>(null);
  
  // Модальное окно
  const [selectedSkin, setSelectedSkin] = useState<MarketSkin | null>(null);
  const [isModalOpen, setIsModalOpen] = useState(false);
  
  // Фильтры
  const [search, setSearch] = useState('');
  const [gameType, setGameType] = useState<'rust' | 'cs2'>('rust');
  const [sort, setSort] = useState('popularity_7d');
  const [order, setOrder] = useState<'asc' | 'desc'>('desc');

  // Загрузка скинов
  const loadSkins = useCallback(async (page: number, append: boolean = false) => {
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
        limit: SKINS_PER_PAGE.toString(),
        sort,
        order,
        gameType,
      });
      
      if (search) {
        params.append('search', search);
      }

      const response = await fetch(`/api/market/skins?${params.toString()}`);
      const result = await response.json();
      
      if (result.success) {
        if (append) {
          setSkins((prev) => [...prev, ...result.data.items]);
        } else {
          setSkins(result.data.items);
        }
        setCurrentPage(page);
        setTotalPages(result.data.pagination.totalPages);
        setHasMore(page < result.data.pagination.totalPages);
      } else {
        setError(result.message || 'Ошибка при загрузке скинов');
        setHasMore(false);
      }
    } catch (err: any) {
      console.error('Error fetching skins:', err);
      setError(err.message || 'Ошибка при загрузке скинов');
      setHasMore(false);
    } finally {
      if (append) {
        setIsLoadingMore(false);
      } else {
        setLoading(false);
      }
    }
  }, [search, gameType, sort, order, hasMore, isLoadingMore]);

  // Загрузка следующей страницы
  const loadMoreSkins = useCallback(() => {
    if (!isLoadingMore && hasMore) {
      loadSkins(currentPage + 1, true);
    }
  }, [currentPage, hasMore, isLoadingMore, loadSkins]);

  // Сброс и загрузка при изменении фильтров (включая начальную загрузку)
  useEffect(() => {
    setSkins([]);
    setCurrentPage(0);
    setHasMore(true);
    loadSkins(1, false);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [search, gameType, sort, order]);

  // Intersection Observer для бесконечной прокрутки
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0].isIntersecting && hasMore && !isLoadingMore && !loading) {
          loadMoreSkins();
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
  }, [hasMore, isLoadingMore, loading, loadMoreSkins]);

  const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
    setSearch(e.target.value);
  };

  const handleGameTypeChange = (newGameType: 'rust' | 'cs2') => {
    setGameType(newGameType);
  };

  const handleSortChange = (newSort: string) => {
    // Если кликнули на уже активную сортировку - меняем направление
    if (sort === newSort) {
      setOrder(order === 'asc' ? 'desc' : 'asc');
    } else {
      // Если выбрали другую сортировку - устанавливаем её и сбрасываем на asc
      setSort(newSort);
      setOrder('asc');
    }
  };


  return (
    <div className="market-skins">
      <div className="container">
        {/* Заголовок */}
        <div className="market-skins__header">
          <div className="market-skins__title-section">
            <h1 className="page-title">Маркет скинов</h1>
            <p className="page-description">
              Купить скины для Rust и CS2
            </p>
          </div>
        </div>

        {/* Переключатель игры */}
        <div className="market-skins__game-switcher">
          <Tabs
            tabs={[
              { id: 'rust', label: 'Rust' },
              { id: 'cs2', label: 'CS2' },
            ]}
            activeTab={gameType}
            onChange={(tabId) => handleGameTypeChange(tabId as 'rust' | 'cs2')}
          />
        </div>

        {/* Поиск и фильтры */}
        <div className="market-skins__search-filters">
          <Input
            type="text"
            placeholder="Поиск по названию..."
            value={search}
            onChange={handleSearch}
            style={{ maxWidth: '300px' }}
          />
          <div className="market-skins__sort-filters">
            <Button
              onClick={() => handleSortChange('our_price')}
              variant={sort === 'our_price' ? 'primary' : 'secondary'}
            >
              По цене
              {sort === 'our_price' && (
                <Icon name={order === 'asc' ? 'arrow-up' : 'arrow-down'} fontSize="small" />
              )}
            </Button>
            <Button
              onClick={() => handleSortChange('popularity_7d')}
              variant={sort === 'popularity_7d' ? 'primary' : 'secondary'}
            >
              По популярности
              {sort === 'popularity_7d' && (
                <Icon name={order === 'asc' ? 'arrow-up' : 'arrow-down'} fontSize="small" />
              )}
            </Button>
          </div>
        </div>

        {/* Список скинов */}
        {error ? (
          <div className="market-skins__empty">
            <p>{error}</p>
          </div>
        ) : skins.length === 0 ? (
          <div className="market-skins__empty">
            <p>Скины не найдены</p>
          </div>
        ) : (
          <>
            <div className="market-skins__grid">
              {skins.map((skin) => (
                <ProductCard
                  key={skin.id}
                  id={skin.id}
                  name={skin.name}
                  ruName={skin.ru_name}
                  image={skin.image_url}
                  image300={skin.image300_url}
                  price={skin.price}
                  priceReal={skin.our_price}
                  avgPrice={skin.avg_price}
                  quality={skin.ru_quality}
                  textColor={skin.text_color}
                  bgColor={skin.bg_color}
                  category={skin.category}
                  gameType={skin.game_type}
                  isStatTrak={skin.is_stat_trak}
                  isSkin={true}
                  onClick={(id) => {
                    const clickedSkin = skins.find(s => s.id === id);
                    if (clickedSkin) {
                      setSelectedSkin(clickedSkin);
                      setIsModalOpen(true);
                    }
                  }}
                />
              ))}
            </div>

            {/* Элемент для отслеживания скролла */}
            {hasMore && (
              <div ref={observerTarget} style={{ minHeight: '20px', width: '100%' }}>
                {isLoadingMore && (
                  <div style={{ textAlign: 'center', padding: '20px' }}>
                    <p>Загрузка...</p>
                  </div>
                )}
              </div>
            )}
          </>
        )}
        
        {loading && skins.length === 0 && (
          <div style={{ textAlign: 'center', padding: '40px' }}>
            <p>Загрузка...</p>
          </div>
        )}
      </div>

      {/* Модальное окно для скина */}
      {selectedSkin && (
        <ProductModal
          product={{
            id: selectedSkin.id,
            name: selectedSkin.name,
            ru_name: selectedSkin.ru_name,
            image: selectedSkin.image_url,
            image300: selectedSkin.image300_url,
            price: selectedSkin.price,
            priceReal: selectedSkin.our_price,
            isSkin: true,
            ru_quality: selectedSkin.ru_quality,
            text_color: selectedSkin.text_color,
            bg_color: selectedSkin.bg_color,
            category: selectedSkin.category,
            game_type: selectedSkin.game_type,
            is_stat_trak: selectedSkin.is_stat_trak,
          }}
          isOpen={isModalOpen}
          onClose={() => {
            setIsModalOpen(false);
            setSelectedSkin(null);
          }}
          onPurchaseSuccess={() => {
            // Обновление баланса будет происходить через WebSocket или обновление страницы
          }}
          isGuest={false} // TODO: получить из контекста или пропсов
        />
      )}
    </div>
  );
}

