'use client';

import React, { useRef, useState, useEffect } from 'react';
import CategoryCard from './CategoryCard';
import Icon from '@/components/icons/Icon';

interface Category {
  id: number;
  name: string;
  image?: string;
}

interface CategoriesProps {
  categories: Category[];
  activeCategoryId?: number | null;
  onCategoryClick?: (id: number) => void;
}

export default function Categories({ categories, activeCategoryId, onCategoryClick }: CategoriesProps) {
  const scrollContainerRef = useRef<HTMLDivElement>(null);
  const [showRightArrow, setShowRightArrow] = useState(false);
  const [showLeftArrow, setShowLeftArrow] = useState(false);

  // Проверяем, нужны ли стрелки
  useEffect(() => {
    const checkScroll = () => {
      if (scrollContainerRef.current) {
        const { scrollWidth, clientWidth, scrollLeft } = scrollContainerRef.current;
        const hasMoreContent = scrollWidth > clientWidth;
        const isScrolledToStart = scrollLeft <= 10; // 10px tolerance
        const isScrolledToEnd = scrollLeft + clientWidth >= scrollWidth - 10; // 10px tolerance
        
        setShowLeftArrow(hasMoreContent && !isScrolledToStart);
        setShowRightArrow(hasMoreContent && !isScrolledToEnd);
      }
    };

    checkScroll();
    window.addEventListener('resize', checkScroll);
    if (scrollContainerRef.current) {
      scrollContainerRef.current.addEventListener('scroll', checkScroll);
    }

    return () => {
      window.removeEventListener('resize', checkScroll);
      if (scrollContainerRef.current) {
        scrollContainerRef.current.removeEventListener('scroll', checkScroll);
      }
    };
  }, [categories]);

  const scrollRight = () => {
    if (scrollContainerRef.current) {
      const scrollAmount = 200; // Прокручиваем на 200px
      scrollContainerRef.current.scrollBy({
        left: scrollAmount,
        behavior: 'smooth',
      });
    }
  };

  const scrollLeft = () => {
    if (scrollContainerRef.current) {
      const scrollAmount = 200; // Прокручиваем на 200px
      scrollContainerRef.current.scrollBy({
        left: -scrollAmount,
        behavior: 'smooth',
      });
    }
  };

  return (
    <section className="categories">
      <div className="categories__carousel-wrapper">
        {showLeftArrow && (
          <button
            type="button"
            className="categories__arrow categories__arrow--left"
            onClick={scrollLeft}
            aria-label="Прокрутить влево"
          >
            <Icon name="arrow-left" fontSize="large" />
          </button>
        )}
        <div className="categories__carousel" ref={scrollContainerRef}>
          {categories.map((category) => (
            <CategoryCard
              key={category.id}
              {...category}
              isActive={activeCategoryId === category.id}
              onClick={onCategoryClick}
            />
          ))}
        </div>
        {showRightArrow && (
          <button
            type="button"
            className="categories__arrow categories__arrow--right"
            onClick={scrollRight}
            aria-label="Прокрутить вправо"
          >
            <Icon name="arrow-right" fontSize="large" />
          </button>
        )}
      </div>
    </section>
  );
}

