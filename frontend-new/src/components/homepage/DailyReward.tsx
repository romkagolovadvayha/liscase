'use client';

import React, { useEffect, useRef } from 'react';
import Button from '@/components/forms/Button';
import { useCSSVariable } from '@/hooks/useCSSVariable';

interface DailyRewardProps {
  botLink?: string;
  bonusImage?: string;
  bonusImageVideo?: string;
}

export default function DailyReward({ botLink = '#', bonusImage, bonusImageVideo }: DailyRewardProps) {
  // Получаем значения из CSS переменных, если не переданы через пропсы
  const defaultBonusImage = useCSSVariable('bonusBlockImage', '');
  const defaultBonusImageVideo = useCSSVariable('bonusBlockImageVideo', '');

  // Используем переданные значения или значения из CSS переменных
  const finalBonusImage = bonusImage || defaultBonusImage;
  const finalBonusImageVideo = bonusImageVideo || defaultBonusImageVideo;
  
  // Ref для видео элемента
  const videoRef = useRef<HTMLVideoElement>(null);
  
  // Обработка ленивой загрузки видео
  useEffect(() => {
    if (typeof window === 'undefined' || !videoRef.current) return;
    
    const video = videoRef.current;
    
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting && entry.target === video) {
            // Загружаем и воспроизводим видео
            video.load();
            video.play().catch((error) => {
              console.warn('Failed to play video:', error);
            });
            observer.unobserve(video);
          }
        });
      },
      { rootMargin: '50px' }
    );
    
    observer.observe(video);
    
    return () => {
      observer.unobserve(video);
    };
  }, [finalBonusImageVideo]);

  return (
    <section className="daily-reward">
      <h2 className="daily-reward__title">Ежедневная награда</h2>
      <p className="daily-reward__description">
        <span>
          Переходи в наш{' '}
          <a href={botLink} className="p0" rel="nofollow" target="_blank">
            Telegram-бот
          </a>{' '}
          для получения ежедневного бонуса.
        </span>
        <span>
          Напиши <span className="btn-clipboard" style={{ color: 'var(--link-color-default)' }}>/bonus</span> в боте,
          чтобы получить награду
        </span>
      </p>

      {finalBonusImageVideo ? (
        <video
          ref={videoRef}
          className="daily-reward__image"
          playsInline
          loop
          autoPlay
          muted
          data-lazy="true"
          preload="none"
          poster={finalBonusImage}
        >
          <source type="video/webm" src={finalBonusImageVideo} />
        </video>
      ) : (
        finalBonusImage && <img src={finalBonusImage} alt="symbol" loading="lazy" className="daily-reward__image" />
      )}
      <Button 
        as="a" 
        href={botLink} 
        rel="nofollow" 
        target="_blank" 
        variant="secondary"
        rightIcon="arrow-right"
      >
        Получить бонус
      </Button>
    </section>
  );
}

