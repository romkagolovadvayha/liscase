import React from 'react';
import ReactDOM from 'react-dom/client';
import bridge from '@vkontakte/vk-bridge';
import { AdaptivityProvider, ConfigProvider } from '@vkontakte/vkui';
import '@vkontakte/vkui/dist/vkui.css';

import WidgetApp from './WidgetApp';

// Инициализация VK Bridge
bridge.send('VKWebAppInit');

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <ConfigProvider>
      <AdaptivityProvider>
        <WidgetApp />
      </AdaptivityProvider>
    </ConfigProvider>
  </React.StrictMode>
);

