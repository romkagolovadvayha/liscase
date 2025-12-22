'use client';

import React from 'react';
import ServersList from './widgets/ServersList';
import ProfileBlock from './widgets/ProfileBlock';
import BuildingsBlock from './widgets/BuildingsBlock';
import TeamsBlock from './widgets/TeamsBlock';
import KillsBlock from './widgets/KillsBlock';
import TopBlock from './widgets/TopBlock';
import LiveBlock from './widgets/LiveBlock';
import BlogTableOfContents from './widgets/BlogTableOfContents';
import BlogLatestPosts from './widgets/BlogLatestPosts';
import '@/styles/sidebar.scss';

export interface SidebarProps {
  servers?: any[];
  projectStats?: {
    online?: number;
  };
  profile?: any;
  buildings?: any;
  teams?: any;
  kills?: any;
  top?: any;
  live?: any;
}

export default function Sidebar({
  servers,
  projectStats,
  profile,
  buildings,
  teams,
  kills,
  top,
  live,
}: SidebarProps) {
  return (
    <aside className="sidebar">
      <BlogTableOfContents />
      {servers && <ServersList servers={servers} projectStats={projectStats} />}
      <BlogLatestPosts />
      {profile && <ProfileBlock data={profile} />}
      {buildings && <BuildingsBlock data={buildings} />}
      {teams && <TeamsBlock data={teams} />}
      {kills && <KillsBlock data={kills} />}
      {top && <TopBlock data={top} />}
      {live && <LiveBlock data={live} />}
    </aside>
  );
}

