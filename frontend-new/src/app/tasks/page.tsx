import React from 'react';
import TasksClient from '@/components/tasks/TasksClient';
import type { Metadata } from 'next';

export const metadata: Metadata = {
  title: 'Бонусы и задания',
  description: 'Система заданий и бонусов для игроков.',
};

export default function TasksPage() {
  return <TasksClient />;
}




