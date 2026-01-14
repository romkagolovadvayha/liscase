'use client';

import React, { useState, useEffect, useMemo } from 'react';
import { toastSuccess, toastError } from '@/lib/toast';
import Icon from '@/components/icons/Icon';
import Button from '@/components/forms/Button';
import Link from 'next/link';
import { Tooltip } from 'react-tooltip';
import apiClient from '@/lib/api/client';
import { isAuthenticated } from '@/lib/api/auth';
import { useSettings } from '@/hooks/useSettings';
import { getModalLightImage } from '@/lib/utils/settingsImage';
import '@/styles/product-modal.scss';

interface SubDrop {
  id: number;
  drop_id: number;
  count: number;
  name: string;
  price: number;
  discount?: number;
  image?: string;
  description?: string;
}

interface Product {
  id: number;
  name: string;
  description?: string;
  image?: string;
  price: number;
  priceReal?: number;
  count?: number;
  discount?: number;
  drop_type?: number;
  subDrops?: SubDrop[];
  floating_price_percent?: number;
  // Поля для скинов
  isSkin?: boolean;
  ru_name?: string | null;
  image300?: string;
  category?: string | null;
  ru_quality?: string | null;
  text_color?: string | null;
  bg_color?: string | null;
  game_type?: 'rust' | 'cs2';
  is_stat_trak?: boolean;
}

interface ProductModalProps {
  product: Product | null;
  isOpen: boolean;
  onClose: () => void;
  onPurchaseSuccess?: (newBalance: number) => void;
  isGuest?: boolean;
}

export default function ProductModal({
  product: productProp,
  isOpen,
  onClose,
  onPurchaseSuccess,
  isGuest: isGuestProp = false,
}: ProductModalProps) {
  const { data: settings } = useSettings();
  const modalLightImage = getModalLightImage(settings);
  const [product, setProduct] = useState<Product | null>(null);
  const [loading, setLoading] = useState(false);
  const [purchasing, setPurchasing] = useState(false);
  const [quantity, setQuantity] = useState(1);
  const [selectedDropId, setSelectedDropId] = useState<number | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [isUserAuthenticated, setIsUserAuthenticated] = useState(() => {
    // Инициализируем состояние авторизации при монтировании
    if (typeof window !== 'undefined') {
      return isAuthenticated();
    }
    return !isGuestProp;
  });

  // Отслеживаем изменения авторизации
  useEffect(() => {
    if (typeof window !== 'undefined') {
      const checkAuth = () => {
        const authenticated = isAuthenticated();
        setIsUserAuthenticated(authenticated);
      };

      checkAuth();

      // Слушаем изменения в localStorage (срабатывает только при изменениях из других вкладок)
      const handleStorageChange = (e: StorageEvent) => {
        if (e.key === 'access_token') {
          checkAuth();
        }
      };

      window.addEventListener('storage', handleStorageChange);
      
      return () => {
        window.removeEventListener('storage', handleStorageChange);
      };
    }
  }, []);

  // Определяем isGuest: проверяем реальную авторизацию, а не только проп
  const isGuest = !isUserAuthenticated;

  useEffect(() => {
    if (isOpen) {
      // При открытии модального окна проверяем авторизацию
      if (typeof window !== 'undefined') {
        setIsUserAuthenticated(isAuthenticated());
      }

      if (productProp) {
        // Для скинов загружаем данные из API
        if (productProp.isSkin) {
          loadSkinData(productProp.id);
        } else if ((productProp.drop_type === 2 || productProp.drop_type === 3) && !productProp.subDrops) {
          // Если у продукта нет subDrops, но они нужны (TYPE_SET или TYPE_SELECT), загружаем их
          loadSubDrops(productProp.id);
        } else {
          setProduct(productProp);
          if (productProp.drop_type === 3 && productProp.subDrops && productProp.subDrops.length > 0) {
            // Для TYPE_SELECT выбираем первый вариант по умолчанию
            setSelectedDropId(productProp.subDrops[0].drop_id);
          }
        }
      }
    } else {
      setProduct(null);
      setQuantity(1);
      setSelectedDropId(null);
      setError(null);
    }
  }, [isOpen, productProp]);

  const loadSkinData = async (skinId: number) => {
    setLoading(true);
    setError(null);
    try {
      // TODO: /v1/skins/{id} endpoint пока не реализован в новом API
      const response = await apiClient.get(`/skins/${skinId}`);
      const result = response.data;

      if (result.success) {
        setProduct({ ...result.data, isSkin: true });
      } else {
        setProduct(productProp);
        setError(result.message || 'Не удалось загрузить данные скина');
      }
    } catch (error) {
      console.error('Error loading skin data:', error);
      setProduct(productProp);
      setError('Произошла ошибка при загрузке данных скина');
    } finally {
      setLoading(false);
    }
  };

  const loadSubDrops = async (productId: number) => {
    setLoading(true);
    setError(null);
    try {
      // TODO: /v1/products/{id} endpoint пока не реализован в новом API
      const response = await apiClient.get(`/products/${productId}`);
      const result = response.data;

      if (result.success) {
        setProduct(result.data);
        if (result.data.drop_type === 3 && result.data.subDrops?.length > 0) {
          // Для TYPE_SELECT выбираем первый вариант по умолчанию
          setSelectedDropId(result.data.subDrops[0].drop_id);
        }
      } else {
        // Если не удалось загрузить subDrops, используем данные из пропсов
        setProduct(productProp);
        setError(result.message || 'Не удалось загрузить детали продукта');
      }
    } catch (error) {
      console.error('Error loading product details:', error);
      // Если произошла ошибка, используем данные из пропсов
      setProduct(productProp);
      setError('Произошла ошибка при загрузке деталей продукта');
    } finally {
      setLoading(false);
    }
  };

  const handleQuantityChange = (delta: number) => {
    const newQuantity = Math.max(1, quantity + delta);
    setQuantity(newQuantity);
  };

  const handleQuantityInput = (e: React.ChangeEvent<HTMLInputElement>) => {
    const value = parseInt(e.target.value) || 1;
    setQuantity(Math.max(1, value));
  };

  const handlePurchase = async () => {
    if (!product || purchasing) return;

    setPurchasing(true);
    setError(null);

    try {
      // TODO: /v1/skins/{id}/buy и /v1/products/{id}/buy endpoints пока не реализованы в новом API
      const endpoint = product.isSkin 
        ? `/skins/${product.id}/buy`
        : `/products/${product.id}/buy`;
      
      const response = await apiClient.post(endpoint, 
        product.isSkin 
          ? {} // Для скинов не передаем quantity и drop_id
          : { quantity, drop_id: selectedDropId }
      );

      const result = response.data;

      if (result.success) {
        toastSuccess(result.message || 'Предмет успешно приобретен!');
        if (onPurchaseSuccess && result.data?.newBalance !== undefined) {
          onPurchaseSuccess(result.data.newBalance);
        }
        onClose();
      } else {
        const errorMessage = result.message || 'Произошла ошибка при покупке';
        toastError(errorMessage);
        setError(errorMessage);
      }
    } catch (error: any) {
      console.error('Error purchasing product:', error);
      const errorMessage = error.message || 'Произошла ошибка при покупке';
      toastError(errorMessage);
      setError(errorMessage);
    } finally {
      setPurchasing(false);
    }
  };

  const handleOverlayClick = (e: React.MouseEvent) => {
    if (e.target === e.currentTarget) {
      onClose();
    }
  };

  if (!isOpen) return null;

  // Форматирование цены: округление вверх до целого
  const formatPrice = (price: number): string => {
    const roundedPrice = Math.ceil(price);
    return new Intl.NumberFormat('ru-RU', {
      style: 'currency',
      currency: 'RUB',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(roundedPrice);
  };

  const totalPrice = product && product.priceReal ? Math.ceil(product.priceReal * quantity) : 0;

  return (
    <div className="product-modal-overlay" onClick={handleOverlayClick}>
      <div className={`product-modal ${product?.drop_type === 3 || product?.drop_type === 2 ? 'product-modal--wide' : ''}`}>
        {/* Снежинки для эффекта объема */}
        <span className="product-modal__snowflake product-modal__snowflake--1">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--2">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--3">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--4">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--5">❄</span>
        <span className="product-modal__snowflake product-modal__snowflake--6">❄</span>
        <button className="product-modal__close" onClick={onClose}>
          <Icon name="close" fontSize="small" />
        </button>

        {loading ? (
          <div className="product-modal__loading">
            <Icon name="loading" fontSize="large" />
            <span>Загрузка...</span>
          </div>
        ) : error && !product ? (
          <div className="product-modal__error">
            <p>{error}</p>
            <Button onClick={onClose} variant="secondary">
              Закрыть
            </Button>
          </div>
        ) : product ? (
          <>
            <header className="product-modal__header">
              <h2 className="product-modal__title">
                {product.isSkin && product.ru_name ? product.ru_name : product.name}
              </h2>
            </header>
            <div className="product-modal__content">
              {product.isSkin ? (
                // Для скинов показываем изображение без светового эффекта
                <figure className="product-modal__image-wrapper" style={{ textAlign: 'center', marginBottom: '24px' }}>
                  <img
                    src={product.image300 || product.image || '/images/placeholder.png'}
                    alt={product.isSkin && product.ru_name ? product.ru_name : product.name}
                    style={{ maxWidth: '200px', maxHeight: '200px', margin: '0 auto' }}
                  />
                  {product.ru_quality && (
                    <p style={{ 
                      fontSize: '14px', 
                      color: product.text_color || '#4ade80',
                      marginTop: '16px',
                      fontWeight: 600
                    }}>
                      {product.ru_quality}
                    </p>
                  )}
                </figure>
              ) : product.drop_type !== 2 && product.drop_type !== 3 ? (
                <figure className="product-modal__image-wrapper">
                  <img
                    src={modalLightImage}
                    alt=""
                    className="product-modal__light"
                  />
                  <img
                    src={product.image || '/images/placeholder.png'}
                    alt={product.name}
                    className="product-modal__image"
                  />
                </figure>
              ) : null}

              <div className="product-modal__info">
                {product.drop_type === 2 && product.subDrops && product.subDrops.length > 0 && (
                  <div className="product-modal__subdrops">
                    {product.subDrops.map((subDrop) => (
                      <div key={subDrop.id} className="product-modal__subdrop">
                        <div className="product-modal__subdrop-image-wrapper">
                          <img
                            src={subDrop.image || '/images/placeholder.png'}
                            alt={subDrop.name}
                            className="product-modal__subdrop-image"
                          />
                          {subDrop.count && subDrop.count > 1 && (
                            <div className="product-modal__subdrop-boost">x{subDrop.count}</div>
                          )}
                        </div>
                        <p className="product-modal__subdrop-title">{subDrop.name}</p>
                      </div>
                    ))}
                  </div>
                )}

                {product.drop_type === 3 && product.subDrops && product.subDrops.length > 0 && (
                  <>
                    <div className="product-modal__select-header">
                      <h3 className="product-modal__select-title">
                        Выберите предмет
                        <span 
                          className="product-modal__select-title-hint"
                          data-tooltip-id="select-hint-tooltip"
                          data-tooltip-content="Выберите один из доступных вариантов товара"
                        >
                          <Icon name="info" fontSize="small" />
                        </span>
                      </h3>
                    </div>
                    <div className="product-modal__select-grid">
                      {product.subDrops.map((subDrop) => {
                        const isSelected = selectedDropId === subDrop.drop_id;
                        const subDropPriceReal = subDrop.discount
                          ? subDrop.price * (1 - subDrop.discount / 100)
                          : subDrop.price;

                        return (
                          <div
                            key={subDrop.id}
                            className={`product-modal__select-card ${isSelected ? 'product-modal__select-card--active' : ''}`}
                            onClick={() => setSelectedDropId(subDrop.drop_id)}
                          >
                            <div className="product-modal__select-card-image-wrapper">
                              <img
                                src={subDrop.image || '/images/placeholder.png'}
                                alt={subDrop.name}
                                className="product-modal__select-card-image"
                              />
                              <div className="product-modal__select-card-price">
                                {Math.ceil(subDropPriceReal)} <span className="icons icons_16px icons_16px_coin"></span>
                              </div>
                            </div>
                            <div className="product-modal__select-card-name">
                              {subDrop.name}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                    <Tooltip id="select-hint-tooltip" />
                  </>
                )}

                {product.isSkin ? (
                  // Для скинов показываем подтверждение покупки
                  <>
                    <p className="product-modal__description" style={{ textAlign: 'center', marginBottom: '24px' }}>
                      Вы уверены, что хотите купить этот скин за {product.priceReal ? formatPrice(product.priceReal) : formatPrice(product.price)}?
                    </p>
                    <div style={{
                      background: 'var(--background-teritiary)',
                      padding: '16px',
                      borderRadius: 'var(--card-radius)',
                      border: '1px solid var(--border-color-default)',
                      color: 'var(--text-secondary)',
                      textAlign: 'center',
                      fontSize: '14px'
                    }}>
                      <span style={{ marginRight: '8px', verticalAlign: 'middle', display: 'inline-flex', alignItems: 'center' }}>
                        <Icon name="info" fontSize="small" />
                      </span>
                      В случае если вы не примите обмен или не придет трейд, деньги автоматически вернутся на баланс в течение часа.
                    </div>
                  </>
                ) : product.drop_type !== 3 ? (
                  <p className="product-modal__description">
                    {product.description}
                  </p>
                ) : null}

                {product.floating_price_percent != null && Number(product.floating_price_percent) > 0 && (
                  <p className="product-modal__floating-price">
                    На этот товар действует плавающая цена:{' '}
                    <span>+{product.floating_price_percent}%</span> за покупку.
                  </p>
                )}
              </div>
            </div>

            <footer className="product-modal__footer">
              {error && (
                <div className="product-modal__error-message">
                  {error}
                </div>
              )}

              {isGuest ? (
                <button
                  onClick={() => {
                    const apiBaseUrl = process.env.NEXT_PUBLIC_API_BASE_URL || 'http://api.test.prostoj.store';
                    window.location.href = `${apiBaseUrl}/v1/auth/oauth`;
                  }}
                  className="button button-primary"
                  style={{ width: '100%', display: 'flex', justifyContent: 'center' }}
                >
                  <span className="button__text">
                    Войти через Steam
                    <Icon name="steam" faFixedSize={20} />
                  </span>
                </button>
              ) : (
                <div className="product-modal__purchase">
                  <div className="product-modal__purchase-top">
                    <div className="product-modal__purchase-price-wrapper">
                      <div className="product-modal__purchase-price">
                        {(() => {
                          if (product.isSkin) {
                            return formatPrice(product.priceReal || product.price);
                          }
                          if (product.drop_type === 3 && selectedDropId && product.subDrops) {
                            const selectedSubDrop = product.subDrops.find(sd => sd.drop_id === selectedDropId);
                            if (selectedSubDrop) {
                              const subDropPriceReal = selectedSubDrop.discount
                                ? selectedSubDrop.price * (1 - selectedSubDrop.discount / 100)
                                : selectedSubDrop.price;
                              return Math.ceil(subDropPriceReal * quantity);
                            }
                          }
                          return totalPrice;
                        })()} <span className={`icons icons_16px ${product.isSkin ? 'icons_16px_coin_skins' : 'icons_16px_coin'}`}></span>
                      </div>
                      {product.drop_type === 3 && selectedDropId && product.subDrops && (() => {
                        const selectedSubDrop = product.subDrops!.find(sd => sd.drop_id === selectedDropId);
                        if (!selectedSubDrop) return null;
                        return (
                          <div className="product-modal__purchase-selected-name">
                            {selectedSubDrop.name}
                          </div>
                        );
                      })()}
                    </div>
                  </div>
                  <div className="product-modal__purchase-actions" style={product.isSkin ? { flexDirection: 'row', gap: '12px' } : undefined}>
                    {!product.isSkin && (
                      <>
                        <div 
                          className="product-modal__quantity-wrapper"
                          data-tooltip-id="quantity-tooltip"
                          data-tooltip-content={product.count && product.count > 1 
                            ? `Количество единиц в одном товаре: ${product.count} шт.` 
                            : 'Выберите количество товаров для покупки'}
                        >
                          <button
                            type="button"
                            className="product-modal__quantity-btn"
                            onClick={() => handleQuantityChange(-1)}
                            disabled={quantity <= 1 || purchasing || product.drop_type === 1 || product.drop_type === 2}
                          >
                            <Icon name="remove" fontSize="small" />
                          </button>
                          <span className="product-modal__quantity-text">
                            {quantity} шт.
                            {product.count && product.count > 1 && (
                              <span className="product-modal__quantity-count">x{product.count}</span>
                            )}
                          </span>
                          <button
                            type="button"
                            className="product-modal__quantity-btn"
                            onClick={() => handleQuantityChange(1)}
                            disabled={purchasing || product.drop_type === 1 || product.drop_type === 2}
                          >
                            <Icon name="add" fontSize="small" />
                          </button>
                        </div>
                        <Tooltip id="quantity-tooltip" />
                      </>
                    )}
                    <Button
                      variant="secondary"
                      onClick={onClose}
                      disabled={purchasing}
                      style={product.isSkin ? { flex: 1 } : undefined}
                    >
                      Отмена
                    </Button>
                    <Button
                      variant="primary"
                      onClick={handlePurchase}
                      disabled={purchasing}
                      leftIcon={purchasing ? 'loading' : (product.isSkin ? undefined : 'shopping-bag')}
                      style={product.isSkin ? { flex: 1 } : undefined}
                    >
                      {purchasing ? 'Оплата...' : (product.isSkin ? 'Получить' : 'Оплатить')}
                    </Button>
                  </div>
                </div>
              )}
            </footer>
          </>
        ) : null}
      </div>
    </div>
  );
}

