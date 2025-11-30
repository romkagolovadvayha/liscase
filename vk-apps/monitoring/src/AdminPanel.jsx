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
      // Получаем параметры из URL (используем параметры VK)
      const params = new URLSearchParams(window.location.search);
      const groupId = params.get('vk_group_id');
      
      // Получаем URL виджета (текущая страница с параметром widget=1)
      const widgetUrl = window.location.origin + window.location.pathname + '?widget=1';
      const widgetCode = `<iframe src="${widgetUrl}" width="100%" height="600" frameborder="0" style="border: none;"></iframe>`;
      
      // Пробуем добавить виджет через VK Bridge
      try {
        await bridge.send('VKWebAppShowCommunityWidgetPreviewBox', {
          group_id: groupId ? parseInt(groupId) : undefined,
          type: 'text',
          code: widgetCode
        });
        
        setAdded(true);
      } catch (error) {
        console.error('Error showing widget preview:', error);
        // Если метод не поддерживается, показываем инструкцию
        showInstructions(widgetUrl);
      }
    } catch (error) {
      console.error('Error adding widget:', error);
      alert('Ошибка при добавлении виджета: ' + (error.error_data?.error_description || error.message || 'Неизвестная ошибка'));
    } finally {
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
                    icon={<div style={{ fontSize: '64px', marginBottom: '16px' }}>✓</div>}
                    header="Виджет успешно добавлен!"
                  >
                    Виджет теперь отображается на главной странице вашего сообщества.
                    Вы можете закрыть это окно.
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
