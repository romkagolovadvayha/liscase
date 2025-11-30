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
  // Проверяем, установлено ли приложение в сообщество (есть vk_group_id)
  const params = new URLSearchParams(window.location.search);
  const groupId = params.get('vk_group_id');
  const isAppInstalled = !!groupId; // Если есть vk_group_id, значит приложение уже установлено
  
  const [addingApp, setAddingApp] = useState(false);
  const [addingWidget, setAddingWidget] = useState(false);
  const [appAdded, setAppAdded] = useState(isAppInstalled); // Устанавливаем true, если приложение уже установлено
  const [widgetAdded, setWidgetAdded] = useState(false);

  // Шаг 1: Добавить приложение в сообщество
  const handleAddToCommunity = async () => {
    if (!bridge) {
      alert('Ошибка: VK Bridge не инициализирован. Убедитесь, что вы открыли приложение в ВКонтакте.');
      return;
    }

    setAddingApp(true);

    try {
      // Получаем параметры из URL
      const params = new URLSearchParams(window.location.search);
      const appId = params.get('vk_app_id');
      
      if (!appId) {
        alert('Ошибка: не удалось определить ID приложения.');
        setAddingApp(false);
        return;
      }

      // Используем VKWebAppAddToCommunity для добавления приложения в сообщество
      const result = await bridge.send('VKWebAppAddToCommunity', {
        app_id: parseInt(appId)
      });
      
      console.log('Add to community result:', result);
      setAppAdded(true);
    } catch (error) {
      console.error('Error adding app to community:', error);
      console.error('Error details:', error.error_data || error);
      alert('Ошибка при добавлении приложения в сообщество: ' + (error.error_data?.error_description || error.message || 'Неизвестная ошибка'));
    } finally {
      setAddingApp(false);
    }
  };

  // Шаг 2: Установить виджет
  const handleInstallWidget = async () => {
    if (!bridge) {
      alert('Ошибка: VK Bridge не инициализирован. Убедитесь, что вы открыли приложение в ВКонтакте.');
      return;
    }

    setAddingWidget(true);

    try {
      // Получаем параметры из URL
      const params = new URLSearchParams(window.location.search);
      const groupId = params.get('vk_group_id');
      
      if (!groupId) {
        alert('Ошибка: не удалось определить ID сообщества.');
        setAddingWidget(false);
        return;
      }

      // Получаем URL виджета
      const widgetUrl = window.location.origin + window.location.pathname.replace(/\/$/, '') + '/widget.html?widget=1';
      
      // Для плагинов сообществ используем VKWebAppShowCommunityWidgetPreviewBox
      // Пробуем разные форматы
      let result;
      let success = false;
      
      // Вариант 1: iframe с url
      try {
        result = await bridge.send('VKWebAppShowCommunityWidgetPreviewBox', {
          group_id: Math.abs(parseInt(groupId)),
          type: 'iframe',
          url: widgetUrl
        });
        console.log('Widget preview result (iframe):', result);
        success = true;
      } catch (iframeError) {
        console.log('Iframe type failed, trying text with URL...', iframeError);
        
        // Вариант 2: text с простым URL (без HTML)
        try {
          result = await bridge.send('VKWebAppShowCommunityWidgetPreviewBox', {
            group_id: Math.abs(parseInt(groupId)),
            type: 'text',
            code: widgetUrl
          });
          console.log('Widget preview result (text URL):', result);
          success = true;
        } catch (textError) {
          console.error('Text type also failed:', textError);
          throw textError; // Пробрасываем ошибку дальше
        }
      }
      
      if (success) {
        setWidgetAdded(true);
      }
    } catch (error) {
      console.error('Error installing widget:', error);
      console.error('Error details:', error.error_data || error);
      alert('Ошибка при установке виджета: ' + (error.error_data?.error_description || error.message || 'Неизвестная ошибка'));
    } finally {
      setAddingWidget(false);
    }
  };



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
                      subheader="Отображает список серверов с количеством игроков онлайн и возможностью копирования команды подключения."
                      style={{ marginBottom: '16px' }}
                    />

                    {!appAdded ? (
                      <>
                        <Text style={{ marginBottom: '12px', display: 'block', color: 'var(--vkui--color_text_secondary)' }}>
                          Шаг 1: Сначала добавьте приложение в сообщество
                        </Text>
                        <Button
                          size="l"
                          stretched
                          onClick={handleAddToCommunity}
                          loading={addingApp}
                          disabled={!bridge || addingApp}
                          style={{ marginBottom: '16px' }}
                        >
                          {addingApp ? 'Добавление...' : 'Добавить приложение в сообщество'}
                        </Button>
                      </>
                    ) : (
                      <>
                        {!isAppInstalled && (
                          <Banner
                            mode="success"
                            header="Приложение добавлено в сообщество!"
                            style={{ marginBottom: '16px' }}
                          />
                        )}
                        <Text style={{ marginBottom: '12px', display: 'block', color: 'var(--vkui--color_text_secondary)' }}>
                          {isAppInstalled ? 'Установите виджет на главную страницу сообщества' : 'Шаг 2: Теперь установите виджет на главную страницу'}
                        </Text>
                        {!widgetAdded ? (
                          <Button
                            size="l"
                            stretched
                            onClick={handleInstallWidget}
                            loading={addingWidget}
                            disabled={!bridge || addingWidget}
                            mode="primary"
                          >
                            {addingWidget ? 'Установка...' : 'Установить виджет на главную страницу'}
                          </Button>
                        ) : (
                          <Banner
                            mode="success"
                            header="Виджет успешно установлен!"
                            subheader="Виджет теперь отображается на главной странице вашего сообщества."
                          />
                        )}
                      </>
                    )}

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
