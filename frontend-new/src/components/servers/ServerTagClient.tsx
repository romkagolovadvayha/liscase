'use client';

import React from 'react';
import ServerCard from './ServerCard';
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
  tag: ServerTag;
  servers: any[];
}

export default function ServerTagClient({ tag, servers }: ServerTagClientProps) {
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

