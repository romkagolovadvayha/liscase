'use client';

import React, { useState, useEffect } from 'react';
import { useParams } from 'next/navigation';
import ServerCard from './ServerCard';
import apiClient from '@/lib/api/client';
import '@/styles/servers.scss';

interface ServerTag {
  id: number;
  name: string;
  title?: string;
  link_name: string;
  short_description?: string;
  description?: string;
  color: string;
}

interface ServerTagClientProps {
  tag?: ServerTag;
  servers?: any[];
}

export default function ServerTagClient({ tag: initialTag, servers: initialServers }: ServerTagClientProps) {
  const params = useParams();
  const linkName = params?.linkName as string;
  
  const [tag, setTag] = useState<ServerTag | null>(initialTag || null);
  const [servers, setServers] = useState<any[]>(initialServers || []);
  const [isLoading, setIsLoading] = useState(!initialTag || !initialServers);

  useEffect(() => {
    if (!initialTag && linkName) {
      setIsLoading(true);
      apiClient.get(`/servers/tag/${linkName}`)
        .then(response => {
          if (response.data.success) {
            setTag(response.data.data?.tag || null);
            setServers(response.data.data?.servers || []);
          }
        })
        .catch(error => {
          console.error('Failed to fetch server tag data:', error);
        })
        .finally(() => {
          setIsLoading(false);
        });
    }
  }, [initialTag, linkName]);

  if (isLoading) {
    return (
      <div className="servers_page">
        <div className="page-stats__block-without-hover">
          <div className="servers_page_empty">
            <p>Загрузка...</p>
          </div>
        </div>
      </div>
    );
  }

  if (!tag) {
    return (
      <div className="servers_page">
        <div className="page-stats__block-without-hover">
          <div className="servers_page_empty">
            <p>Тег не найден</p>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="servers_page">
      <div className="page-stats__block-without-hover">
        <h1 className="page-title mb-24">
          {tag.title || tag.name}
        </h1>
        
        {tag.description && (
          <div 
            className="tinymce-content"
            dangerouslySetInnerHTML={{ __html: tag.description }}
          />
        )}

        <div className="servers_page_items mt-20">
          {servers.length === 0 ? (
            <div className="servers_page_empty">
              <p>Серверы с этим тегом не найдены</p>
            </div>
          ) : (
            servers.map((server) => (
              <ServerCard key={server.id} server={server} />
            ))
          )}
        </div>
      </div>
    </div>
  );
}

