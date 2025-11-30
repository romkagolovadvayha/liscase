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
  const isAppInstalled = !!groupId; // Если есть vk_group_id, значит приложение уже установлено
  
  const [addingApp, setAddingApp] = useState(false);
  const [addingWidget, setAddingWidget] = useState(false);
  const [appAdded, setAppAdded] = useState(isAppInstalled); // Устанавливаем true, если приложение уже установлено
  const [widgetAdded, setWidgetAdded] = useState(false);
  const [uploadingLogo, setUploadingLogo] = useState(false);
  const [logoIconId, setLogoIconId] = useState(null);
  const fileInputRef = useRef(null);

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
      // Получаем параметры из URL
      const params = new URLSearchParams(window.location.search);
      const groupId = params.get('vk_group_id');
      
      if (!groupId) {
        alert('Ошибка: не удалось определить ID сообщества.');
        setAddingWidget(false);
        return;
      }

      // Получаем параметры для формирования внутренней ссылки ВК
      const appId = params.get('vk_app_id');
      const apiUrl = params.get('api_url') || 'https://api.prostoj.store/servers';
      
      // Формируем внутреннюю ссылку ВК на приложение (только vk.com, vk.me и т.д. разрешены)
      // Убираем URL из виджета, так как внешние ссылки не разрешены
      
      // Загружаем данные о серверах для формирования виджета
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
      
      // Функция для форматирования онлайн как прогресс-бар
      const formatOnlineProgress = (online, max) => {
        const onlineValue = online || 0;
        const maxValue = max || 1;
        const percentage = Math.round((onlineValue / (maxValue - 30)) * 100);
        
        // Создаем прогресс-бар из 4 блоков
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
        
        // Формат: 🟩🟩⬜️⬜️ 👤 73/150 (49%)
        return `${progressBar} 👤 ${onlineValue}/${maxValue}`;
      };
      
      // Функция для форматирования названия сервера с типом вайпа
      const formatServerName = (server) => {
        let name = server.name || 'Сервер';
        
        // Добавляем тип вайпа к названию
        if (server.wipe_type_text) {
          name = `${name} (${server.wipe_type_text})`;
        } else if (server.wipe_type) {
          // Если wipe_type_text нет, но есть wipe_type, формируем вручную
          if (server.wipe_type === 7) {
            name = `${name} Недельный`;
          } else if (server.wipe_type === 14) {
            name = `${name} Двухнедельный`;
          } else if (server.wipe_type === 30) {
            name = `${name} Месячный`;
          }
        }
        
        return name.substring(0, 50);
      };
      
      // Формируем тело таблицы из данных серверов (максимум 6 строк)
      // Добавляем icon_id в первую колонку, если логотип загружен
      // В третьей колонке показываем текстовый IP вместо статуса
      const tableBody = serversData.slice(0, 6).map(server => {
        const firstCell = {
          text: formatServerName(server)
        };
        
        // Добавляем icon_id, если логотип загружен (только для первой ячейки в строке)
        if (logoIconId) {
          firstCell.icon_id = logoIconId;
        }
        
        return [
          firstCell,
          {
            text: formatOnlineProgress(server.online, server.max),
            align: "right"
          },
          {
            text: (server.text_ip || server.ip || '—').substring(0, 50),
            align: "right"
          }
        ];
      });
      
      // Если нет данных, добавляем заглушку
      if (tableBody.length === 0) {
        const placeholderFirstCell = {
          text: "Загрузка данных..."
        };
        if (logoIconId) {
          placeholderFirstCell.icon_id = logoIconId;
        }
        
        tableBody.push([
          placeholderFirstCell,
          {
            text: "—",
            align: "center"
          },
          {
            text: "—",
            align: "center"
          }
        ]);
      }
      
      // Создаем объект виджета с данными
      // Убираем все внешние URL, так как виджеты ВК поддерживают только внутренние ссылки
      const widgetObject = {
        title: "Мониторинг серверов",
        body: tableBody
        // more и more_url убраны, так как внешние ссылки не разрешены
      };
      
      // Формируем код виджета: return { ... };
      const widgetCode = 'return ' + JSON.stringify(widgetObject) + ';';
      
      console.log('Widget code to send:', widgetCode);
      console.log('Widget object:', widgetObject);
      
      const result = await bridge.send('VKWebAppShowCommunityWidgetPreviewBox', {
        group_id: Math.abs(parseInt(groupId)),
        type: 'table',
        code: widgetCode
      });
      
      console.log('Widget preview result:', result);
      setWidgetAdded(true);
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
                          Шаг 2: Загрузите логотип для виджета (опционально, 24x24px)
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
                                // Проверяем размер изображения (должно быть 24x24px)
                                const img = new Image();
                                const objectUrl = URL.createObjectURL(file);
                                img.onload = () => {
                                  URL.revokeObjectURL(objectUrl);
                                  console.log('Image loaded, dimensions:', img.width, 'x', img.height);
                                  if (img.width !== 24 || img.height !== 24) {
                                    alert(`Размер изображения должен быть точно 24x24px. Текущий размер: ${img.width}x${img.height}px\n\nПожалуйста, измените размер изображения до 24x24px и попробуйте снова.`);
                                    e.target.value = ''; // Очищаем input
                                    return;
                                  }
                                  console.log('Image size validated: 24x24px, proceeding with upload');
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
