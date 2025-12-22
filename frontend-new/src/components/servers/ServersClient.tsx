'use client';

import React, { useState, useEffect, useMemo } from 'react';
import ServerCard from './ServerCard';
import Icon from '@/components/icons/Icon';
import Button from '@/components/forms/Button';
import Link from 'next/link';
import '@/styles/servers.scss';

interface ProjectStats {
  online: number;
  users: number;
  count: number;
}

interface ServerTag {
  id: number;
  name: string;
  link_name: string;
  color: string;
  title?: string;
  short_description?: string;
}

interface Server {
  id: number;
  tags: ServerTag[];
  [key: string]: any;
}

interface ServersClientProps {
  projectStats: ProjectStats;
}

export default function ServersClient({ projectStats }: ServersClientProps) {
  const [servers, setServers] = useState<Server[]>([]);
  const [loading, setLoading] = useState(true);
  const [selectedTagId, setSelectedTagId] = useState<number | null>(null);

  useEffect(() => {
    const fetchServers = async () => {
      try {
        const response = await fetch('/api/servers');
        const result = await response.json();
        
        if (result.success) {
          setServers(result.data);
        }
      } catch (error) {
        console.error('Error fetching servers:', error);
      } finally {
        setLoading(false);
      }
    };

    fetchServers();
  }, []);

  // Получаем все уникальные теги
  const allTags = useMemo(() => {
    const tagMap = new Map<number, ServerTag>();
    servers.forEach((server) => {
      server.tags?.forEach((tag) => {
        if (!tagMap.has(tag.id)) {
          tagMap.set(tag.id, tag);
        }
      });
    });
    return Array.from(tagMap.values());
  }, [servers]);

  // Фильтрация серверов по выбранному тегу
  const filteredServers = useMemo(() => {
    if (!selectedTagId) return servers;
    return servers.filter((server) =>
      server.tags?.some((tag) => tag.id === selectedTagId)
    );
  }, [servers, selectedTagId]);

  const handleTagClick = (tagId: number) => {
    setSelectedTagId(selectedTagId === tagId ? null : tagId);
  };

  return (
    <div className="servers_page">
      <div className="page-stats__block-without-hover">
        <div className="relative">
          <h1 className="page-title">
            Сервера Rust для комфортной игры
          </h1>
          <div className="seo-content">
            <p className="mt-12 p1 text-text-teritiary">
              Вы можете выбрать любой из доступных серверов в зависимости от предпочитаемого режима игры.
            </p>
            <ul className="servers_page_description_list mt-24">
              <li className="servers_page_description_list_item">
                <span className="servers_page_description_list_item_image">
                  <Icon name="dns" fontSize="small" />
                </span>
                <span className="servers_page_description_list_item_text">
                  Физическое расположение в Москве
                </span>
              </li>
              <li className="servers_page_description_list_item">
                <span className="servers_page_description_list_item_image">
                  <Icon name="calendar" fontSize="small" />
                </span>
                <span className="servers_page_description_list_item_text">
                  Регулярные вайпы строго по расписанию
                </span>
              </li>
              <li className="servers_page_description_list_item">
                <span className="servers_page_description_list_item_image">
                  <Icon name="person" fontSize="small" />
                </span>
                <span className="servers_page_description_list_item_text">
                  Активная модерация
                </span>
              </li>
              <li className="servers_page_description_list_item">
                <span className="servers_page_description_list_item_image">
                  <Icon name="crown" fontSize="small" />
                </span>
                <span className="servers_page_description_list_item_text">
                  Раздача скинов каждый час
                </span>
              </li>
              <li className="servers_page_description_list_item">
                <span className="servers_page_description_list_item_image">
                  <Icon name="bar-chart" fontSize="small" />
                </span>
                <span className="servers_page_description_list_item_text">
                  Сервера с онлайн 1000+ игроков
                </span>
              </li>
              <li className="servers_page_description_list_item">
                <span className="servers_page_description_list_item_image">
                  <Icon name="dns" fontSize="small" />
                </span>
                <span className="servers_page_description_list_item_text">
                  Сервера без лагов и фризов
                </span>
              </li>
            </ul>
          </div>
        </div>

        {/* Фильтр по тегам */}
        {allTags.length > 0 && (
          <div className="servers_page_tags_filter mt-32 mb-24">
            <div className="servers_page_tags_filter_label">
              <span className="p2 text-text-secondary">Фильтр по категориям:</span>
            </div>
            <div className="servers_page_tags_filter_list">
              {allTags.map((tag) => (
                <button
                  key={tag.id}
                  className={`servers_page_tags_filter_item ${selectedTagId === tag.id ? 'active' : ''}`}
                  onClick={() => handleTagClick(tag.id)}
                  style={{
                    backgroundColor: selectedTagId === tag.id ? tag.color : 'transparent',
                    borderColor: tag.color,
                    color: selectedTagId === tag.id ? '#fff' : tag.color,
                  }}
                >
                  {tag.name}
                </button>
              ))}
            </div>
          </div>
        )}
        
        <div className="servers_page_items">
          {loading ? (
            <div className="servers_page_loading">
              <Icon name="loading" fontSize="large" />
              <span>Загрузка серверов...</span>
            </div>
          ) : servers.length === 0 ? (
            <div className="servers_page_empty">
              <Icon name="dns" fontSize="large" />
              <p>Серверы не найдены</p>
            </div>
          ) : (
            servers.map((server) => {
              const hasSelectedTag = !selectedTagId || server.tags?.some((tag) => tag.id === selectedTagId);
              return (
                <div
                  key={server.id}
                  className={`servers_page_item_wrapper ${!hasSelectedTag ? 'servers_page_item_blurred' : ''}`}
                >
                  <ServerCard server={server} />
                </div>
              );
            })
          )}
        </div>

        {/* SEO Блок */}
        <section className="servers_page_seo mt-48">
          <h2 className="h3">
            Rust сервера Prostoj — как выбрать подходящий
          </h2>
          <p className="mt-16 p1 text-text-secondary">
            На проекте Prostoj доступны классические и ускоренные режимы с разной сложностью, онлайном и расписанием вайпов. Выбирайте сервер по пингу, размеру карты и лимиту команды — подключение в один клик из браузера. Все сервера хостятся на мощном железе и проходят регулярные перезагрузки для стабильного FPS и низкого пинга.
          </p>

          {/* Внутренняя перелинковка */}
          <div className="servers_page_seo_links mt-20">
            <Button as={Link} href="/wipe-calendar" variant="secondary" size="medium">
              Календарь вайпов Rust
            </Button>
            <Button as={Link} href="/raid-table" variant="secondary" size="medium">
              Калькулятор рейда
            </Button>
            <Button as={Link} href="/posts" variant="secondary" size="medium">
              Гайды и новости Rust
            </Button>
            <Button as={Link} href="/custom-skins" variant="secondary" size="medium">
              Кастомные скины сообщества
            </Button>
          </div>
        </section>

        {/* SEO FAQ */}
        <section className="servers_page_faq mt-48">
          <h2 className="h3">
            Частые вопросы о серверах Prostoj
          </h2>

          <div className="servers_page_faq_list mt-16">
            <details className="servers_page_faq_item">
              <summary className="p1">
                <strong>Когда следующий вайп на сервере?</strong>
              </summary>
              <div className="p2 mt-8">
                Недельные сервера вайпаются каждую неделю, 14-дневные — раз в 2 недели. После глобального вайпа в первый четверг месяца дополнительных вайпов в эту неделю нет. Актуальные даты смотрите в{' '}
                <Link href="/wipe-calendar" className="text-text-primary">
                  календаре вайпов
                </Link>.
              </div>
            </details>

            <details className="servers_page_faq_item mt-12">
              <summary className="p1">
                <strong>Как быстро подключиться к серверу?</strong>
              </summary>
              <div className="p2 mt-8">
                Нажмите «Подключиться к серверу» в карточке — откроется Steam с прямым подключением. Либо скопируйте команду вида{' '}
                <code className="servers_page_faq_code">connect IP:PORT</code>{' '}
                и вставьте в консоль Rust.
              </div>
            </details>

            <details className="servers_page_faq_item mt-12">
              <summary className="p1">
                <strong>Какой сервер выбрать новичку?</strong>
              </summary>
              <div className="p2 mt-8">
                Рекомендуем начать с карт малого/среднего размера и серверов с лимитом команды. Так легче освоиться и найти союзников.
              </div>
            </details>

            <details className="servers_page_faq_item mt-12">
              <summary className="p1">
                <strong>Разрешены ли моды и донат?</strong>
              </summary>
              <div className="p2 mt-8">
                Игровой баланс не ломаем: на серверах действуют общие правила, донат отключён там, где это указано на карточке. За нарушения — санкции по правилам.
              </div>
            </details>
          </div>
        </section>
      </div>
    </div>
  );
}
