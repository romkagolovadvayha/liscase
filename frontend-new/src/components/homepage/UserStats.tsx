'use client';

import React, { useEffect, useRef } from 'react';
import classNames from 'classnames';
import Button from '@/components/forms/Button';
import Icon from '@/components/icons/Icon';
import { useCSSVariable } from '@/hooks/useCSSVariable';
import { useAnimatedCounter } from '@/hooks/useAnimatedCounter';

interface StatItem {
  label: string;
  value: string | number;
  image?: string;
}

interface Award {
  id: number;
  name: string;
  image: string;
  completed: boolean;
}

interface UserStatsProps {
  isGuest?: boolean;
  username?: string;
  projectStats?: {
    users: number;
    online: number;
    count: number;
  };
  userStats?: {
    kills?: number;
    deaths?: number;
    kd?: number;
    scientists?: number;
    'sulfur.ore'?: number;
    'metal.ore'?: number;
    stones?: number;
    wood?: number;
  };
  awards?: Award[];
  awardsStats?: {
    completed: number;
    total: number;
  };
  userStatsLink?: string;
  serverActiveTag?: string;
  statsImage?: string;
  statsImageVideo?: string;
  notAuthImage?: string;
  activeVip?: {
    expires_at: string;
  } | null;
  activeVipTimestamp?: number;
}

export default function UserStats({
  isGuest = false,
  username,
  projectStats,
  userStats,
  awards = [],
  awardsStats,
  userStatsLink,
  serverActiveTag,
  statsImage,
  statsImageVideo,
  notAuthImage,
  activeVip,
  activeVipTimestamp,
}: UserStatsProps) {
  // Получаем значения из CSS переменных, если не переданы через пропсы
  const defaultStatsImage = useCSSVariable('statsBlockImage', '');
  const defaultStatsImageVideo = useCSSVariable('statsBlockImageVideo', '');
  const defaultNotAuthImage = useCSSVariable('image-not-auth', '');

  // Используем переданные значения или значения из CSS переменных
  const finalStatsImage = statsImage || defaultStatsImage;
  const finalStatsImageVideo = statsImageVideo || defaultStatsImageVideo;
  const finalNotAuthImage = notAuthImage || defaultNotAuthImage;
  
  // Refs для видео элементов
  const videoRef1 = useRef<HTMLVideoElement>(null);
  const videoRef2 = useRef<HTMLVideoElement>(null);
  
  // Обработка ленивой загрузки видео
  useEffect(() => {
    if (typeof window === 'undefined') return;
    
    const videos = [videoRef1.current, videoRef2.current].filter(Boolean) as HTMLVideoElement[];
    
    if (videos.length === 0) return;
    
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting && entry.target instanceof HTMLVideoElement) {
            const video = entry.target;
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
    
    videos.forEach((video) => {
      if (video) {
        observer.observe(video);
      }
    });
    
    return () => {
      videos.forEach((video) => {
        if (video) {
          observer.unobserve(video);
        }
      });
    };
  }, [finalStatsImageVideo]);

  // Анимированные значения для боевой статистики (вызываем ДО условного return)
  const animatedKills = useAnimatedCounter({ 
    target: userStats?.kills || 0, 
    duration: 1500,
    enabled: !isGuest && !!userStats 
  });
  const animatedDeaths = useAnimatedCounter({ 
    target: userStats?.deaths || 0, 
    duration: 1500,
    enabled: !isGuest && !!userStats 
  });
  const animatedKd = useAnimatedCounter({ 
    target: userStats?.kd || 0, 
    duration: 1500,
    enabled: !isGuest && !!userStats 
  });
  const animatedScientists = useAnimatedCounter({ 
    target: userStats?.scientists || 0, 
    duration: 1500,
    enabled: !isGuest && !!userStats 
  });

  // Анимированные значения для ресурсов
  const animatedSulfurOre = useAnimatedCounter({ 
    target: userStats?.['sulfur.ore'] || 0, 
    duration: 1500,
    enabled: !isGuest && !!userStats 
  });
  const animatedMetalOre = useAnimatedCounter({ 
    target: userStats?.['metal.ore'] || 0, 
    duration: 1500,
    enabled: !isGuest && !!userStats 
  });
  const animatedStones = useAnimatedCounter({ 
    target: userStats?.stones || 0, 
    duration: 1500,
    enabled: !isGuest && !!userStats 
  });
  const animatedWood = useAnimatedCounter({ 
    target: userStats?.wood || 0, 
    duration: 1500,
    enabled: !isGuest && !!userStats 
  });

  if (isGuest) {
    return (
      <section className="user user_not-authorized">
        <h1 className="user__title main_title">Лучшие сервера для комфортной игры!</h1>
        <p className="user__description">Войдите в систему, чтобы начать играть</p>
        <div className="user__stats stats">
          {projectStats && (
            <>
              <div className="stats__item">
                <p>Игроков</p>
                <p>{(projectStats.users || 0).toLocaleString('ru-RU')}</p>
              </div>
              <div className="stats__item">
                <p>Онлайн</p>
                <p>{(projectStats.online || 0).toLocaleString('ru-RU')}</p>
              </div>
              <div className="stats__item">
                <p>Серверов</p>
                <p>{(projectStats.count || 0).toLocaleString('ru-RU')}</p>
              </div>
            </>
          )}
        </div>
        {finalStatsImageVideo ? (
          <video
            ref={videoRef1}
            className="user__image"
            playsInline
            loop
            autoPlay
            muted
            data-lazy="true"
            preload="none"
            poster={finalStatsImage}
          >
            <source type="video/webm" src={finalStatsImageVideo} />
          </video>
        ) : (
          finalNotAuthImage && (
            <img 
              src={finalNotAuthImage} 
              alt="farmer" 
              className="user__image" 
              loading="lazy" 
            />
          )
        )}
        <div className="user__stats_footer">
          <Button 
            as="a" 
            href="/auth/oauth?authclient=steam" 
            rel="nofollow" 
            variant="primary" 
            rightIcon="steam"
            faIconSize="xl"
          >
            Войти через Steam
          </Button>
          {serverActiveTag && (
            <Button 
              as="a" 
              href={`/servers/${serverActiveTag}`} 
              variant="secondary"
            >
              Статистика игроков
            </Button>
          )}
        </div>
      </section>
    );
  }

  const combatStats: StatItem[] = [
    { label: 'Убийств', value: Math.round(animatedKills) },
    { label: 'Смертей', value: Math.round(animatedDeaths) },
    { label: 'K/D', value: animatedKd.toFixed(2) },
    { label: 'Убито ботов', value: Math.round(animatedScientists) },
  ];

  const resourceStats: StatItem[] = [
    { label: 'Серная руда', value: Math.round(animatedSulfurOre), image: '/images/user-stats/gold.png' },
    { label: 'Железная руда', value: Math.round(animatedMetalOre), image: '/images/user-stats/iron_stone.png' },
    { label: 'Камень', value: Math.round(animatedStones), image: '/images/user-stats/stone.png' },
    { label: 'Дерево', value: Math.round(animatedWood), image: '/images/user-stats/wood.png' },
  ];

  return (
    <section className="user">
      <h1 className="user__title">{username}</h1>
      <div className="user__stats-wrapper">
        <div className="user__stats stats">
          {combatStats.map((stat, index) => (
            <div key={index} className="stats__item">
              <p>{stat.label}</p>
              <p>{typeof stat.value === 'number' ? stat.value.toLocaleString('ru-RU') : stat.value}</p>
            </div>
          ))}
        </div>
        <div className="user__stats stats">
          {resourceStats.map((stat, index) => (
            <div key={index} className="stats__item stat" title={stat.label}>
              {stat.image && <img src={stat.image} alt={stat.label} className="stat__image" loading="lazy" />}
              <p className="p0">{typeof stat.value === 'number' ? stat.value.toLocaleString('ru-RU') : stat.value}</p>
            </div>
          ))}
        </div>

        {/* Раздел наград - показываем всегда для авторизованных пользователей */}
        {!isGuest && (
          <div className="user__awards awards">
            <div className="awards__title">
              Награды
              <span 
                className="icons icons_24px icons_24px_info icons_hover"
                data-tooltip-id="awards-tooltip"
                data-tooltip-content="Выполни все задания, чтобы получить награду"
              >
                <Icon name="info" fontSize="small" />
              </span>
            </div>
            <div className="awards__wrapper">
              <div className="awards__list">
                {awards && awards.length > 0 ? (
                  awards.map((award) => (
                    <img
                      key={award.id}
                      src={award.image}
                      alt={award.name}
                      className={classNames('awards__image', { 'awards__image--completed': award.completed })}
                      data-bs-toggle="tooltip"
                      data-bs-placement="right"
                      title={award.name}
                      loading="lazy"
                    />
                  ))
                ) : awardsStats ? (
                  // Если данные загружены, но наград нет, показываем пустое состояние
                  <div className="awards__placeholder">Нет выполненных заданий с наградами</div>
                ) : (
                  // Показываем скелетон пока данные загружаются
                  <>
                    {[1, 2, 3, 4, 5, 6, 7].map((i) => (
                      <div key={i} className="awards__image awards__image--skeleton" />
                    ))}
                  </>
                )}
              </div>
              {awardsStats ? (
                <a href="/tasks" className="awards__stats">
                  выполнено {awardsStats.completed} из {awardsStats.total} заданий
                </a>
              ) : (
                <a href="/tasks" className="awards__stats">
                  Перейти к заданиям
                </a>
              )}
            </div>
          </div>
        )}
      </div>
      {finalStatsImageVideo ? (
        <video
          ref={videoRef2}
          className="user__image"
          playsInline
          loop
          autoPlay
          muted
          data-lazy="true"
          preload="none"
          poster={finalStatsImage}
        >
          <source type="video/webm" src={finalStatsImageVideo} />
        </video>
      ) : (
        finalStatsImage && <img src={finalStatsImage} alt="farmer" className="user__image" loading="lazy" />
      )}
      <div className="user__stats_footer">
        {userStatsLink ? (
          <Button as="a" href={userStatsLink} variant="primary">
            Моя статистика
          </Button>
        ) : (
          serverActiveTag && (
            <Button as="a" href={`/servers/${serverActiveTag}`} variant="primary">
              Статистика игроков
            </Button>
          )
        )}
        <Button as="a" href="/user/profile" variant="secondary">
          Мой профиль
        </Button>
      </div>
    </section>
  );
}

