import React from 'react';
import AdminPanel from './AdminPanel';
import MonitoringWidget from './App';

// Определение режима работы
const getMode = () => {
  const params = new URLSearchParams(window.location.search);
  const isWidget = params.get('widget') === '1';
  const vkIsAppUser = params.get('vk_is_app_user') === '1';
  const vkViewerGroupRole = params.get('vk_viewer_group_role');
  
  // Если явно указан параметр widget=1 - это виджет
  if (isWidget) {
    return 'widget';
  }
  
  // Если vk_is_app_user=1 И vk_viewer_group_role=admin - показываем админку
  if (vkIsAppUser && vkViewerGroupRole === 'admin') {
    return 'admin';
  }
  
  // По умолчанию - виджет (когда уже установлен)
  return 'widget';
};

function WidgetApp() {
  const mode = getMode();

  if (mode === 'admin') {
    return <AdminPanel />;
  }

  return <MonitoringWidget />;
}

export default WidgetApp;
