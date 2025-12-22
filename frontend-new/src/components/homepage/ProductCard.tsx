'use client';

import React from 'react';
import classNames from 'classnames';

interface ProductCardProps {
  id: number;
  name: string;
  image: string;
  price?: number;
  priceReal?: number;
  discount?: number;
  count?: number;
  blocked?: boolean;
  categoryId?: number;
  onClick?: (id: number) => void;
  // Дополнительные поля для скинов
  ruName?: string | null;
  image300?: string;
  avgPrice?: number | null;
  quality?: string | null;
  textColor?: string | null;
  bgColor?: string | null;
  category?: string | null;
  gameType?: 'rust' | 'cs2';
  isStatTrak?: boolean | number;
  isSkin?: boolean; // Флаг, что это скин
}

export default function ProductCard({
  id,
  name,
  image,
  price = 0,
  priceReal = 0,
  discount,
  count,
  blocked = false,
  onClick,
  // Поля для скинов
  ruName,
  image300,
  avgPrice,
  quality,
  textColor,
  bgColor,
  category,
  gameType,
  isStatTrak = false,
  isSkin = false,
}: ProductCardProps) {
  const handleClick = () => {
    if (!blocked && onClick) {
      onClick(id);
    }
  };

  // Для скинов используем русское название, если есть
  const displayName = isSkin && ruName ? ruName : name;
  const displayImage = isSkin && image300 ? image300 : image;

  // Функция форматирования цены: округление вверх до целого, денежный формат без копеек
  const formatPrice = (price: number): string => {
    const roundedPrice = Math.ceil(price);
    return new Intl.NumberFormat('ru-RU', {
      style: 'currency',
      currency: 'RUB',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(roundedPrice);
  };

  return (
    <div
      className={classNames('category-card', { 'show-modal-link': !blocked, 'category-card--blocked': blocked })}
      data-category-id={id}
      onClick={handleClick}
      aria-disabled={blocked}
    >
      <div className="category-card__snowflakes">
        <span className="category-card__snowflake">❄</span>
        <span className="category-card__snowflake">❅</span>
        <span className="category-card__snowflake">❆</span>
        <span className="category-card__snowflake">❄</span>
        <span className="category-card__snowflake">❅</span>
        <span className="category-card__snowflake">❆</span>
        <span className="category-card__snowflake">❄</span>
        <span className="category-card__snowflake">❅</span>
        <span className="category-card__snowflake">❆</span>
      </div>
      {!isSkin && count && count > 1 && (
        <div 
          className="category-card__boost" 
          data-tooltip-id="product-count-tooltip"
          data-tooltip-content={`Количество единиц в одном товаре: ${count} шт.`}
        >
          x{count}
        </div>
      )}
      {isSkin && (isStatTrak === true || isStatTrak === 1) && (
        <div 
          className="category-card__boost" 
          data-tooltip-id="stat-trak-tooltip"
          data-tooltip-content="StatTrak™"
        >
          ST
        </div>
      )}
      {!isSkin && discount && discount > 0 && <div className="category-card__discount">-{discount}%</div>}
      <div className="category-card__image-wrapper" style={{ position: 'relative' }}>
        <img className="category-card__image" src={displayImage} alt={displayName} loading="lazy" />
        {isSkin && gameType === 'cs2' && category && (
          <span
            className="category-card__category-badge"
            style={{
              position: 'absolute',
              bottom: '8px',
              left: '8px',
              color: '#fff',
              backgroundColor: 'rgba(0, 0, 0, 0.7)',
              padding: '2px 6px',
              borderRadius: '4px',
              fontSize: '10px',
              fontWeight: 600,
              lineHeight: 1.2,
              zIndex: 2,
            }}
          >
            {category}
          </span>
        )}
        {isSkin && quality && (
          <span
            className="category-card__quality-badge"
            style={{
              position: 'absolute',
              bottom: (gameType === 'cs2' && category) ? '28px' : '8px',
              left: '8px',
              color: textColor || '#fff',
              backgroundColor: bgColor || 'rgba(0, 0, 0, 0.7)',
              padding: '2px 6px',
              borderRadius: '4px',
              fontSize: '10px',
              fontWeight: 600,
              lineHeight: 1.2,
              zIndex: 2,
            }}
          >
            {quality}
          </span>
        )}
      </div>
      <p className="category-card__title">{displayName}</p>
      {!blocked && (
        <div className="category-card__price">
          {isSkin ? (
            // Для скинов показываем нашу цену
            <span className="category-card__price-current">
              {formatPrice(priceReal > 0 ? priceReal : price)} <span className="icons icons_16px icons_16px_coin_skins"></span>
            </span>
          ) : (
            // Для обычных товаров
            <>
              {priceReal > 0 ? (
                <>
                  {priceReal !== price && price > 0 && <span className="category-card__price-old">{formatPrice(price)}</span>}
                  <span className="category-card__price-current">
                    {formatPrice(priceReal)} <span className="icons icons_16px icons_16px_coin"></span>
                  </span>
                </>
              ) : (
                <span className="category-card__price-buy">Выбрать товар</span>
              )}
            </>
          )}
        </div>
      )}
      {blocked && <div className="category-card__blocked">Вайп-блок</div>}
    </div>
  );
}

