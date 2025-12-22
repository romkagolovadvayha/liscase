'use client';

import React from 'react';
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

