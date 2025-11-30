import React, { useState } from 'react';
import {
  AppRoot,
  SplitLayout,
  SplitCol,
  View,
  Panel,
  Group,
  Card,
  Text,
  Button,
  Banner,
  Placeholder,
  Div
} from '@vkontakte/vkui';
import bridge from '@vkontakte/vk-bridge';

function AdminPanel() {
  const [adding, setAdding] = useState(false);
  const [added, setAdded] = useState(false);

  const handleAddWidget = async () => {
    if (!bridge) {
      alert('Ошибка: VK Bridge не инициализирован. Убедитесь, что вы открыли приложение в ВКонтакте.');
      return;
    }

    setAdding(true);

    try {
      // Получаем параметры из URL
      const params = new URLSearchParams(window.location.search);
      const groupId = params.get('vk_group_id');
      
      if (!groupId) {
        alert('Ошибка: не удалось определить ID сообщества.');
        setAdding(false);
        return;
      }

      // Получаем URL виджета
      const widgetUrl = window.location.origin + window.location.pathname.replace(/\/$/, '') + '/widget.html?widget=1';
      
      // Для плагинов сообществ используем VKWebAppShowCommunityWidgetPreviewBox
      // Это встроенный метод VK для добавления виджетов в сообщество
      try {
        const result = await bridge.send('VKWebAppShowCommunityWidgetPreviewBox', {
          group_id: Math.abs(parseInt(groupId)),
          type: 'text',
          code: `<iframe src="${widgetUrl}" width="100%" height="600" frameborder="0" style="border: none;"></iframe>`
        });
        
        console.log('Widget preview result:', result);
        // После открытия диалога VK обработает добавление виджета
        setAdded(true);
      } catch (error) {
        console.error('Error showing widget preview:', error);
        console.error('Error details:', error.error_data || error);
        
        // Если метод не работает, открываем страницу настроек сообщества
        const groupIdAbs = Math.abs(parseInt(groupId));
        const settingsUrl = `https://vk.com/club${groupIdAbs}?act=widgets`;
        
        // Пробуем открыть через VK Bridge
        try {
          await bridge.send('VKWebAppOpenURL', {
            url: settingsUrl,
            target: 'internal'
          });
        } catch (openError) {
          // Если и это не работает, открываем напрямую
          window.open(settingsUrl, '_blank');
        }
        
        setAdded(true);
      }
    } catch (error) {
      console.error('Error adding widget:', error);
      alert('Ошибка при добавлении виджета. Проверьте консоль браузера для подробностей.');
      setAdding(false);
    }
  };

  const showInstructions = (widgetUrl) => {
    // Можно показать инструкцию или оставить как есть
    console.log('Widget URL:', widgetUrl);
  };

  if (added) {
    return (
      <AppRoot>
        <SplitLayout>
          <SplitCol>
            <View activePanel="success">
              <Panel id="success">
                <Group>
                  <Placeholder
                    icon={<div style={{ fontSize: '64px', marginBottom: '16px' }}>ℹ️</div>}
                    header="Откройте настройки виджетов"
                  >
                    Откройте страницу настроек виджетов вашего сообщества.
                    В разделе "Виджеты" добавьте встраиваемый виджет и укажите URL виджета.
                    После этого виджет появится на главной странице сообщества.
                  </Placeholder>
                </Group>
              </Panel>
            </View>
          </SplitCol>
        </SplitLayout>
      </AppRoot>
    );
  }

  return (
    <AppRoot>
      <SplitLayout>
        <SplitCol>
          <View activePanel="admin">
            <Panel id="admin">
              <Group>
                <Card style={{ margin: '16px' }}>
                  <Div>
                    <Text weight="semibold" style={{ fontSize: '20px', marginBottom: '8px', display: 'block' }}>
                      Мониторинг серверов
                    </Text>
                    <Text style={{ color: 'var(--vkui--color_text_secondary)', marginBottom: '24px', display: 'block' }}>
                      Добавьте виджет на главную страницу сообщества, чтобы ваши подписчики могли видеть статус серверов в реальном времени.
                    </Text>
                    
                    <Banner
                      mode="info"
                      header="Что делает виджет?"
                      subheader="Отображает список серверов с количеством игроков онлайн и возможностью копирования команды подключения. Данные обновляются автоматически каждые 30 секунд."
                      style={{ marginBottom: '16px' }}
                    />

                    <Button
                      size="l"
                      stretched
                      onClick={handleAddWidget}
                      loading={adding}
                      disabled={!bridge}
                    >
                      {adding ? 'Добавление...' : 'Добавить виджет на главную страницу'}
                    </Button>

                    {!bridge && (
                      <Banner
                        mode="warning"
                        header="Ошибка инициализации"
                        subheader="VK Bridge не инициализирован. Убедитесь, что вы открыли приложение в ВКонтакте."
                        style={{ marginTop: '16px' }}
                      />
                    )}
                  </Div>
                </Card>
              </Group>
            </Panel>
          </View>
        </SplitCol>
      </SplitLayout>
    </AppRoot>
  );
}

export default AdminPanel;
