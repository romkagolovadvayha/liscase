import React from 'react';
import AdminPanel from './AdminPanel';
import MonitoringWidget from './App';

// Определение режима работы
const getMode = () => {
  const params = new URLSearchParams(window.location.search);
  const vkRef = params.get('vk_ref');
  const isWidget = params.get('widget') === '1';
  const isCommunityInstalled = vkRef === 'community_installed_app_popup';
  
  // Если явно указан параметр widget=1 - это виджет
  if (isWidget) {
    return 'widget';
  }
  
  // Если vk_ref указывает на установку в сообщество - это админка
  // Также проверяем роль пользователя - если admin, показываем админку
  if (isCommunityInstalled || params.get('vk_viewer_group_role') === 'admin') {
    // Но только если это первый запуск (community_installed_app_popup)
    // Иначе показываем виджет
    if (isCommunityInstalled) {
      return 'admin';
    }
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
