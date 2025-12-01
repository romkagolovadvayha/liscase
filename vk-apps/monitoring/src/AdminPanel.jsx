import React, { useState, useRef } from 'react';
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
  const vkViewerGroupRole = params.get('vk_viewer_group_role');
  const isAppInstalled = !!groupId; // Если есть vk_group_id, значит приложение уже установлено
  
  // Проверка прав доступа - админка только для администраторов сообщества
  if (vkViewerGroupRole !== 'admin') {
    return (
      <AppRoot>
        <SplitLayout>
          <SplitCol>
            <View activePanel="access-denied">
              <Panel id="access-denied">
                <Group>
                  <Card style={{ margin: '16px' }}>
                    <Placeholder
                      header="Доступ запрещен"
                      text="Доступ к админ-панели виджета имеют только администраторы сообщества."
                    />
                  </Card>
                </Group>
              </Panel>
            </View>
          </SplitCol>
        </SplitLayout>
      </AppRoot>
    );
  }
  
  const [addingApp, setAddingApp] = useState(false);
  const [addingWidget, setAddingWidget] = useState(false);
  const [updatingWidget, setUpdatingWidget] = useState(false);
  const [appAdded, setAppAdded] = useState(isAppInstalled); // Устанавливаем true, если приложение уже установлено
  const [widgetAdded, setWidgetAdded] = useState(false);
  const [uploadingLogo, setUploadingLogo] = useState(false);
  const [logoIconId, setLogoIconId] = useState(null);
  const fileInputRef = useRef(null);

  // Общая функция для создания кода виджета из данных серверов
  const createWidgetCode = (serversData, logoIconIdValue) => {
    // Функция для форматирования онлайн как прогресс-бар
    const formatOnlineProgress = (online, max) => {
      const onlineValue = online || 0;
      const maxValue = max || 1;
      const effectiveMaxValue = Math.max(maxValue, 30); 
      const percentage = Math.round((onlineValue / effectiveMaxValue) * 100);
      
      const totalBlocks = 4;
      const filledBlocks = Math.round((percentage / 100) * totalBlocks);
      
      let progressBar = '';
      for (let i = 0; i < totalBlocks; i++) {
        if (i < filledBlocks) {
          progressBar += '🟩';
        } else {
          progressBar += '⬜️';
        }
      }
      
      return `${onlineValue}/${maxValue} 👤 ${progressBar}`;
    };
    
    // Функция для форматирования названия сервера с типом вайпа
    const formatServerName = (server) => {
      let name = server.name || 'Сервер';
      
      if (server.wipe_type_text) {
        name = `${name} (${server.wipe_type_text})`;
      } else if (server.wipe_type) {
        switch (server.wipe_type) {
          case 7:
            name = `${name} (Недельный)`;
            break;
          case 14:
            name = `${name} (Двухнедельный)`;
            break;
          case 30:
            name = `${name} (Месячный)`;
            break;
        }
      }
      return name.substring(0, 50);
    };
    
    // Формируем тело таблицы из данных серверов (максимум 6 строк)
    const tableBody = serversData.slice(0, 6).map(server => {
      const firstCell = {
        text: formatServerName(server)
      };
      
      if (logoIconIdValue) {
        firstCell.icon_id = logoIconIdValue;
      }
      
      return [
        firstCell,
        {
          text: formatOnlineProgress(server.online, server.max) + " | " + (server.text_ip || server.ip || '—').substring(0, 50),
          align: "right"
        }
      ];
    });
    
    if (tableBody.length === 0) {
      const placeholderFirstCell = {
        text: "Загрузка данных..."
      };
      if (logoIconIdValue) {
        placeholderFirstCell.icon_id = logoIconIdValue;
      }
      
      tableBody.push([
        placeholderFirstCell,
        {
          text: "—",
          align: "right"
        }
      ]);
    }
    
    const widgetObject = {
      title: "Мониторинг серверов",
      "head": [
        {"text": "Сервер"},
        {"text": "Игроки | IP", "align": "right"}
      ],
      body: tableBody
    };
    
    return 'return ' + JSON.stringify(widgetObject) + ';';
  };

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

  // Загрузка логотипа в ВК
  const handleUploadLogo = async (file) => {
    console.log('handleUploadLogo called with file:', file);
    console.log('bridge:', bridge);
    console.log('groupId:', groupId);
    
    if (!bridge) {
      alert('Ошибка: VK Bridge не инициализирован. Убедитесь, что вы открыли приложение в ВКонтакте.');
      return;
    }

    // Получаем параметры из URL
    const params = new URLSearchParams(window.location.search);
    const currentGroupId = groupId || params.get('vk_group_id');
    const accessToken = params.get('vk_access_token_settings') || params.get('access_token');
    
    if (!currentGroupId) {
      alert('Ошибка: не удалось определить ID сообщества. Убедитесь, что приложение установлено в сообщество.');
      return;
    }

    setUploadingLogo(true);
    console.log('Starting logo upload...');

    try {
      // Шаг 1: Получаем адрес сервера для загрузки через прямой вызов API ВК
      console.log('Step 1: Getting upload server URL...');
      
      // Используем серверный прокси для обхода CORS
      // Используем тот же домен API, что и для серверов
      const apiUrlFull = params.get('api_url') || 'https://api.prostoj.store/servers';
      const apiBaseUrl = apiUrlFull.replace('/servers', '').replace(/\/$/, '');
      
      // Используем коллекцию приложения (appWidgets.getAppImageUploadServer)
      // Работает с сервисным ключом, не требует токен пользователя
      console.log('Getting upload server for app image collection (using service key)...');
      
      // Вызываем прокси без параметров - сервер использует сервисный ключ из настроек
      const proxyUrl = `${apiBaseUrl}/vk-widget/get-upload-server`;
      console.log('Calling proxy API:', proxyUrl);
      
      // Вызов через прокси
      const uploadServerResponse = await fetch(proxyUrl);
      const uploadServerData = await uploadServerResponse.json();
      
      console.log('Upload server response:', uploadServerData);

      if (!uploadServerData || !uploadServerData.response || !uploadServerData.response.upload_url) {
        console.error('Upload server result error:', uploadServerData);
        throw new Error(`Не удалось получить адрес сервера загрузки: ${uploadServerData.error?.error_msg || 'Неизвестная ошибка'}`);
      }

      const uploadUrl = uploadServerData.response.upload_url;
      console.log('Upload URL:', uploadUrl);

      // Шаг 2: Загружаем изображение на сервер ВК через прокси (обход CORS)
      console.log('Step 2: Uploading file to VK server via proxy...');
      const uploadFormData = new FormData();
      uploadFormData.append('file', file);
      uploadFormData.append('upload_url', uploadUrl);

      const uploadProxyUrl = `${apiBaseUrl}/vk-widget/upload-image`;
      console.log('Calling upload proxy:', uploadProxyUrl);
      
      const uploadResponse = await fetch(uploadProxyUrl, {
        method: 'POST',
        body: uploadFormData
      });

      console.log('Upload response status:', uploadResponse.status);

      if (!uploadResponse.ok) {
        const errorText = await uploadResponse.text();
        console.error('Upload error response:', errorText);
        throw new Error(`Ошибка загрузки изображения на сервер ВК: ${uploadResponse.status}`);
      }

      const uploadData = await uploadResponse.json();
      console.log('Upload data:', uploadData);

      // Проверяем наличие ошибки в ответе (может быть error_code/error_msg или поле error)
      if (uploadData.error_code || uploadData.error || (uploadData.error_msg && uploadData.error_code)) {
        console.error('Upload result error:', uploadData);
        const errorMsg = uploadData.error_msg || uploadData.error || 'Неизвестная ошибка';
        throw new Error(`Ошибка загрузки изображения: ${errorMsg}${uploadData.error_code ? ' (код: ' + uploadData.error_code + ')' : ''}`);
      }
      
      // Проверяем, что ответ содержит необходимые данные для сохранения
      if (!uploadData || typeof uploadData !== 'object') {
        throw new Error('Получен некорректный ответ от сервера загрузки');
      }

      // Шаг 3: Преобразуем ответ в Base64
      const base64Data = btoa(JSON.stringify(uploadData));
      console.log('Base64 data length:', base64Data.length);

      // Шаг 4: Сохраняем изображение через серверный прокси
      console.log('Step 3: Saving image via proxy...');
      const saveProxyUrl = `${apiBaseUrl}/vk-widget/save-image`;
      
      const saveFormData = new FormData();
      // Для коллекции приложения group_id не требуется
      // Сервер использует сервисный ключ из настроек
      saveFormData.append('image', base64Data);
      
      const saveResponse = await fetch(saveProxyUrl, {
        method: 'POST',
        body: saveFormData
      });
      const saveResult = await saveResponse.json();

      console.log('Save result:', saveResult);

      if (!saveResult || !saveResult.response || !saveResult.response.id) {
        console.error('Save result error:', saveResult);
        throw new Error(`Не удалось сохранить изображение: ${saveResult.error?.error_msg || 'Неизвестная ошибка'}`);
      }

      // Получаем icon_id (id из ответа)
      const iconId = saveResult.response.id;
      setLogoIconId(iconId);
      
      console.log('Logo uploaded successfully, icon_id:', iconId);
      alert('Логотип успешно загружен! ID: ' + iconId);
    } catch (error) {
      console.error('Error uploading logo:', error);
      console.error('Error stack:', error.stack);
      const errorMessage = error.error_data?.error_description || error.error?.error_msg || error.message || 'Неизвестная ошибка';
      alert('Ошибка при загрузке логотипа: ' + errorMessage + '\n\nПроверьте консоль для деталей.');
    } finally {
      setUploadingLogo(false);
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
      const params = new URLSearchParams(window.location.search);
      const groupId = params.get('vk_group_id');
      
      if (!groupId) {
        alert('Ошибка: не удалось определить ID сообщества.');
        setAddingWidget(false);
        return;
      }

      const apiUrl = params.get('api_url') || 'https://api.prostoj.store/servers';
      
      // Загружаем данные о серверах
      let serversData = [];
      try {
        const response = await fetch(apiUrl, {
          method: 'GET',
          headers: { 'Accept': 'application/json' }
        });
        serversData = await response.json();
      } catch (error) {
        console.error('Error loading servers:', error);
      }
      
      const widgetCode = createWidgetCode(serversData, logoIconId);
      
      console.log('Widget code to send:', widgetCode);
      
      const result = await bridge.send('VKWebAppShowCommunityWidgetPreviewBox', {
        group_id: Math.abs(parseInt(groupId)),
        type: 'table',
        code: widgetCode
      });
      
      console.log('Widget preview result:', result);
      
      // Сохраняем информацию о виджете в БД для автоматического обновления через cron
      try {
        const params = new URLSearchParams(window.location.search);
        const apiBaseUrl = apiUrl.replace('/servers', '').replace(/\/$/, '');
        
        // Пытаемся получить ключ доступа сообщества для автоматического обновления
        // ВАЖНО: Для appWidgets.update нужен именно ключ доступа сообщества, а не токен пользователя!
        let communityToken = null;
        try {
          const communityTokenResult = await bridge.send('VKWebAppGetCommunityToken', {
            group_id: Math.abs(parseInt(groupId))
          });
          if (communityTokenResult && communityTokenResult.token) {
            communityToken = communityTokenResult.token;
            console.log('Community token obtained for auto-update');
          }
        } catch (e) {
          console.log('Cannot get community token for auto-update, widget will be saved without token:', e);
          console.log('You will need to manually update the widget or get the community token later');
        }
        
        const saveFormData = new FormData();
        saveFormData.append('group_id', Math.abs(parseInt(groupId)).toString());
        saveFormData.append('app_id', params.get('vk_app_id') || '');
        if (logoIconId) {
          saveFormData.append('logo_icon_id', logoIconId);
        }
        saveFormData.append('api_url', apiUrl);
        if (communityToken) {
          saveFormData.append('access_token', communityToken);
          console.log('Saving community token to database for auto-update');
        } else {
          console.warn('Community token not obtained. Automatic updates via cron will not work until token is saved.');
        }
        
        const saveWidgetUrl = `${apiBaseUrl}/vk-widget/save`;
        const saveResponse = await fetch(saveWidgetUrl, {
          method: 'POST',
          body: saveFormData
        });
        
        const saveResult = await saveResponse.json();
        if (saveResult.success) {
          console.log('Widget info saved to database for auto-update:', saveResult);
          if (!communityToken) {
            console.warn('WARNING: Widget saved without community token. Automatic updates via cron will fail.');
            console.warn('To enable automatic updates, update the widget manually once using the "Update now" button.');
          }
        } else {
          console.warn('Failed to save widget info:', saveResult);
        }
      } catch (error) {
        console.error('Error saving widget info:', error);
        // Не критично, продолжаем
      }
      
      setWidgetAdded(true);
    } catch (error) {
      console.error('Error installing widget:', error);
      console.error('Error details:', error.error_data || error);
      alert('Ошибка при установке виджета: ' + (error.error_data?.error_description || error.message || 'Неизвестная ошибка'));
    } finally {
      setAddingWidget(false);
    }
  };

  // Обновление виджета
  const handleUpdateWidget = async () => {
    if (!bridge) {
      alert('Ошибка: VK Bridge не инициализирован. Убедитесь, что вы открыли приложение в ВКонтакте.');
      return;
    }

    setUpdatingWidget(true);

    try {
      const params = new URLSearchParams(window.location.search);
      const groupId = params.get('vk_group_id');
      
      if (!groupId) {
        alert('Ошибка: не удалось определить ID сообщества.');
        setUpdatingWidget(false);
        return;
      }

      // Получаем ключ доступа сообщества для обновления виджета
      // ВАЖНО: Для appWidgets.update нужен именно ключ доступа сообщества, а не токен пользователя!
      let communityToken = null;
      
      console.log('Attempting to get community token for group_id:', groupId);
      console.log('Available URL parameters:', Array.from(params.entries()));
      
      // Способ 1: Пытаемся получить через VKWebAppGetCommunityToken
      try {
        console.log('Trying VKWebAppGetCommunityToken...');
        const communityTokenResult = await bridge.send('VKWebAppGetCommunityToken', {
          group_id: Math.abs(parseInt(groupId))
        });
        console.log('VKWebAppGetCommunityToken result:', communityTokenResult);
        if (communityTokenResult && (communityTokenResult.token || communityTokenResult.access_token)) {
          communityToken = communityTokenResult.token || communityTokenResult.access_token;
          console.log('Community token obtained via VKWebAppGetCommunityToken');
        }
      } catch (e) {
        console.warn('Cannot get community token via VKWebAppGetCommunityToken:', e);
        console.warn('Error details:', e.error_data || e.message);
      }
      
      // Способ 2: Проверяем параметры URL (может быть передан напрямую)
      if (!communityToken) {
        console.log('Checking URL parameters for token...');
        // Проверяем различные возможные параметры
        const possibleTokenParams = [
          'vk_group_token',
          'vk_community_token',
          'vk_access_token_settings',
          'access_token'
        ];
        
        for (const paramName of possibleTokenParams) {
          const tokenValue = params.get(paramName);
          if (tokenValue) {
            console.log(`Found token in URL parameter: ${paramName}`);
            communityToken = tokenValue;
            break;
          }
        }
      }
      
      // Способ 3: Пытаемся получить токен пользователя с правами app_widget (может не работать для appWidgets.update)
      if (!communityToken) {
        console.warn('Trying to get user token with app_widget scope as last resort...');
        let userToken = params.get('vk_access_token_settings') || params.get('access_token');
        if (!userToken) {
          try {
            const tokenResult = await bridge.send('VKWebAppGetAuthToken', {
              app_id: parseInt(params.get('vk_app_id')),
              scope: 'app_widget'
            });
            if (tokenResult && tokenResult.access_token) {
              userToken = tokenResult.access_token;
              console.log('User token with app_widget scope obtained');
            }
          } catch (e) {
            console.error('Cannot get auth token:', e);
          }
        }
        if (userToken) {
          console.warn('Using user token as fallback (may not work for appWidgets.update)');
          communityToken = userToken;
        }
      }
      
      if (!communityToken) {
        // Формируем понятное сообщение об ошибке с инструкциями
        const groupIdNum = Math.abs(parseInt(groupId));
        const errorMessage = `⚠️ Не удалось получить ключ доступа сообщества для обновления виджета.

📋 Для автоматического обновления через cron требуется ключ доступа сообщества.

🔑 Как получить ключ доступа сообщества:

1. Откройте настройки сообщества:
   https://vk.com/club${groupIdNum}?act=settings

2. Перейдите в раздел "Работа с API"

3. Включите "Доступ к API сообщества" (если еще не включен)

4. Скопируйте "Ключ доступа" сообщества

5. Обратитесь к администратору для сохранения токена в БД через команду:
   php yii vk-widget/save-token ${groupIdNum} <токен>

Или администратор может сохранить токен в таблице vk_widgets в поле access_token (зашифрованный).

После сохранения токена в БД автоматическое обновление через cron будет работать.

Group ID: ${groupIdNum}`;
        
        alert(errorMessage);
        throw new Error('Токен сообщества не получен. См. инструкции в сообщении выше.');
      }
      
      console.log('Using token for widget update (length:', communityToken.length, 'chars)');

      const apiUrl = params.get('api_url') || 'https://api.prostoj.store/servers';
      const apiBaseUrl = apiUrl.replace('/servers', '').replace(/\/$/, '');
      
      // Загружаем актуальные данные о серверах
      let serversData = [];
      try {
        const response = await fetch(apiUrl, {
          method: 'GET',
          headers: { 'Accept': 'application/json' }
        });
        serversData = await response.json();
      } catch (error) {
        console.error('Error loading servers:', error);
      }
      
      const widgetCode = createWidgetCode(serversData, logoIconId);
      
      console.log('Updating widget with code:', widgetCode);
      
      // Обновляем виджет через прокси
      const updateFormData = new FormData();
      updateFormData.append('group_id', Math.abs(parseInt(groupId)).toString());
      updateFormData.append('code', widgetCode);
      updateFormData.append('type', 'table');
      updateFormData.append('access_token', communityToken);
      
      const updateProxyUrl = `${apiBaseUrl}/vk-widget/update`;
      const updateResponse = await fetch(updateProxyUrl, {
        method: 'POST',
        body: updateFormData
      });
      
      const updateResult = await updateResponse.json();
      
      if (updateResult.error || updateResult.error_code) {
        throw new Error(updateResult.error_msg || updateResult.error || 'Ошибка обновления виджета');
      }
      
      console.log('Widget updated successfully:', updateResult);
      
      // Сохраняем токен сообщества в БД для будущих автоматических обновлений через cron
      try {
        const saveFormData = new FormData();
        saveFormData.append('group_id', Math.abs(parseInt(groupId)).toString());
        saveFormData.append('app_id', params.get('vk_app_id') || '');
        if (logoIconId) {
          saveFormData.append('logo_icon_id', logoIconId);
        }
        saveFormData.append('api_url', apiUrl);
        saveFormData.append('access_token', communityToken); // Сохраняем токен для cron
        
        const saveWidgetUrl = `${apiBaseUrl}/vk-widget/save`;
        const saveResponse = await fetch(saveWidgetUrl, {
          method: 'POST',
          body: saveFormData
        });
        
        const saveResult = await saveResponse.json();
        if (saveResult.success) {
          console.log('Community token saved to database for future auto-updates');
        } else {
          console.warn('Failed to save community token:', saveResult);
        }
      } catch (error) {
        console.error('Error saving community token:', error);
        // Не критично, но предупреждаем пользователя
      }
      
      alert('Виджет успешно обновлен! Данные обновлены на главной странице сообщества. Токен сохранен для автоматического обновления через cron.');
    } catch (error) {
      console.error('Error updating widget:', error);
      alert('Ошибка при обновлении виджета: ' + (error.message || 'Неизвестная ошибка'));
    } finally {
      setUpdatingWidget(false);
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
                          Шаг 2: Загрузите логотип для виджета (опционально, 72x72px)
                        </Text>
                        <Text style={{ marginBottom: '12px', display: 'block', color: 'var(--vkui--color_text_secondary)', fontSize: '14px' }}>
                          ВК требует загружать изображения в утроенном размере: для виджета 24x24px нужно загрузить изображение 72x72px
                        </Text>
                        <div style={{ marginBottom: '16px' }}>
                          <input
                            ref={fileInputRef}
                            type="file"
                            id="logoUpload"
                            accept="image/*"
                            style={{ display: 'none' }}
                            onChange={(e) => {
                              console.log('File input changed:', e.target.files);
                              const file = e.target.files?.[0];
                              if (file) {
                                console.log('Selected file:', file.name, file.type, file.size);
                                // Проверяем тип файла
                                if (!file.type.startsWith('image/')) {
                                  alert('Пожалуйста, выберите изображение (PNG, JPG, GIF и т.д.)');
                                  e.target.value = ''; // Очищаем input
                                  return;
                                }
                                // Проверяем размер файла (максимум 5MB)
                                if (file.size > 5 * 1024 * 1024) {
                                  alert('Размер файла не должен превышать 5MB');
                                  e.target.value = ''; // Очищаем input
                                  return;
                                }
                                // Проверяем размер изображения
                                // ВК требует загружать изображения в утроенном размере:
                                // для виджета 24x24px нужно загружать 72x72px (24 * 3 = 72)
                                const img = new Image();
                                const objectUrl = URL.createObjectURL(file);
                                const requiredSize = 72;
                                img.onload = () => {
                                  URL.revokeObjectURL(objectUrl);
                                  console.log('Image loaded, dimensions:', img.width, 'x', img.height);
                                  if (img.width !== requiredSize || img.height !== requiredSize) {
                                    alert(`Размер изображения должен быть точно ${requiredSize}x${requiredSize}px (для виджета 24x24px).\nТекущий размер: ${img.width}x${img.height}px\n\nВК требует загружать изображения в утроенном размере. Пожалуйста, измените размер изображения до ${requiredSize}x${requiredSize}px и попробуйте снова.`);
                                    e.target.value = ''; // Очищаем input
                                    return;
                                  }
                                  console.log(`Image size validated: ${requiredSize}x${requiredSize}px, proceeding with upload`);
                                  handleUploadLogo(file);
                                };
                                img.onerror = () => {
                                  URL.revokeObjectURL(objectUrl);
                                  alert('Не удалось загрузить изображение. Попробуйте другое изображение.');
                                  e.target.value = ''; // Очищаем input
                                };
                                img.src = objectUrl;
                              } else {
                                console.log('No file selected');
                              }
                            }}
                            disabled={uploadingLogo}
                          />
                          <Button
                            size="m"
                            stretched
                            loading={uploadingLogo}
                            disabled={uploadingLogo}
                            mode="secondary"
                            onClick={() => {
                              console.log('Upload button clicked');
                              if (fileInputRef.current) {
                                console.log('Triggering file input click');
                                fileInputRef.current.click();
                              } else {
                                console.error('File input ref is null');
                              }
                            }}
                          >
                            {uploadingLogo ? 'Загрузка...' : logoIconId ? 'Логотип загружен ✓' : 'Загрузить логотип'}
                          </Button>
                        </div>
                        
                        {logoIconId && (
                          <Banner
                            mode="success"
                            header="Логотип загружен!"
                            subheader={`ID: ${logoIconId}`}
                            style={{ marginBottom: '16px' }}
                          />
                        )}
                        
                        <Text style={{ marginBottom: '12px', display: 'block', color: 'var(--vkui--color_text_secondary)' }}>
                          {isAppInstalled ? 'Установите виджет на главную страницу сообщества' : 'Шаг 3: Теперь установите виджет на главную страницу'}
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
                          <>
                            <Banner
                              mode="success"
                              header="Виджет успешно установлен!"
                              subheader="Виджет теперь отображается на главной странице вашего сообщества."
                              style={{ marginBottom: '16px' }}
                            />
                            <Banner
                              mode="info"
                              header="Автоматическое обновление"
                              subheader="Данные виджета будут автоматически обновляться по расписанию (cron). Настройте cron-задачу: php yii vk-widget/update-all"
                              style={{ marginBottom: '16px' }}
                            />
                            <Button
                              size="l"
                              stretched
                              onClick={handleUpdateWidget}
                              loading={updatingWidget}
                              disabled={!bridge || updatingWidget}
                              mode="secondary"
                            >
                              {updatingWidget ? 'Обновление...' : 'Обновить данные виджета сейчас'}
                            </Button>
                            <Text style={{ marginTop: '12px', display: 'block', color: 'var(--vkui--color_text_secondary)', fontSize: '14px' }}>
                              Нажмите для немедленного обновления или дождитесь автоматического обновления по расписанию
                            </Text>
                          </>
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
