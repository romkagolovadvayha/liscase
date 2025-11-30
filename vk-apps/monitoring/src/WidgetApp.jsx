import React from 'react';
import AdminPanel from './AdminPanel';
import MonitoringWidget from './App';

// Определение режима работы
const getMode = () => {
  const params = new URLSearchParams(window.location.search);
  const isWidget = params.get('widget') === '1';
  const vkIsAppUser = params.get('vk_is_app_user') === '1';
  
  // Если явно указан параметр widget=1 - это виджет
  if (isWidget) {
    return 'widget';
  }
  
  // Если vk_is_app_user=1 - показываем админку с кнопкой добавления виджета
  if (vkIsAppUser) {
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
