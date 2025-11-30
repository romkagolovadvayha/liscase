import React, { useState, useEffect } from 'react';
import {
  AppRoot,
  SplitLayout,
  SplitCol,
  View,
  Panel,
  Group,
  Card,
  SimpleCell,
  Avatar,
  Text,
  Button,
  Banner,
  Badge
} from '@vkontakte/vkui';
import { Icon16CopyOutline } from '@vkontakte/icons';
import bridge from '@vkontakte/vk-bridge';

// Получаем параметры из URL
const getUrlParams = () => {
  const params = new URLSearchParams(window.location.search);
  return {
    apiUrl: params.get('api_url') || 'https://api.prostoj.store/servers',
    logoUrl: params.get('logo_url') || '/server_logo.png'
  };
};

function App() {
  const [servers, setServers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [copiedId, setCopiedId] = useState(null);
  const [config, setConfig] = useState(getUrlParams());

  const loadServers = async () => {
    try {
      setLoading(true);
      setError(null);

      const response = await fetch(config.apiUrl, {
        method: 'GET',
        headers: {
          'Accept': 'application/json',
        }
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const data = await response.json();
      setServers(data);
    } catch (err) {
      console.error('Error loading servers:', err);
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadServers();
  }, []);

  const getFillPercentage = (online, max) => {
    if (!max || max === 0) return 0;
    return Math.round((online / max) * 100);
  };

  const getStatusColor = (percentage) => {
    if (percentage >= 90) return '#e74c3c';
    if (percentage >= 70) return '#f39c12';
    return '#2ecc71';
  };

  const parseTags = (tagsString) => {
    if (!tagsString) return [];
    // Разделяем по запятой и очищаем от пробелов
    return tagsString.split(',').map(tag => tag.trim()).filter(tag => tag.length > 0);
  };

  const copyToClipboardFallback = (text, index) => {
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    textArea.style.top = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
      const successful = document.execCommand('copy');
      if (successful) {
        setCopiedId(index);
        setTimeout(() => setCopiedId(null), 2000);
      }
    } catch (err) {
      console.error('Failed to copy:', err);
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
          setCopiedId(index);
          setTimeout(() => setCopiedId(null), 2000);
        }).catch(() => {
          console.error('Clipboard API also failed');
        });
      }
    } finally {
      document.body.removeChild(textArea);
    }
  };

  return (
    <AppRoot>
      <SplitLayout>
        <SplitCol>
          <View activePanel="main">
            <Panel id="main">

              {loading && (
                <Group>
                  <div style={{ padding: '40px', textAlign: 'center' }}>
                    <div
                      style={{
                        width: '48px',
                        height: '48px',
                        border: '4px solid var(--vkui--color_field_background)',
                        borderTop: '4px solid var(--vkui--color_icon_accent)',
                        borderRadius: '50%',
                        animation: 'spin 1s linear infinite',
                        margin: '0 auto 16px'
                      }}
                    />
                    <Text style={{ marginTop: '16px', display: 'block' }}>
                      Загрузка данных...
                    </Text>
                  </div>
                </Group>
              )}

              {error && (
                <Group>
                  <Banner
                    mode="error"
                    header="Ошибка загрузки"
                    subheader={error}
                    actions={
                      <Button onClick={() => loadServers()}>
                        Повторить
                      </Button>
                    }
                  />
                </Group>
              )}


              {!loading && !error && servers.length > 0 && (
                <div style={{ padding: '12px' }}>
                  {servers.map((server, index) => {
                    const isOnline = server.online !== null && server.online !== undefined;
                    const onlineCount = server.online || 0;
                    const maxPlayers = server.max || 0;
                    const fillPercentage = getFillPercentage(onlineCount, maxPlayers);
                    const statusColor = getStatusColor(fillPercentage);
                    const tags = parseTags(server.tags);

                    return (
                      <React.Fragment key={index}>
                        <Card style={{ marginBottom: '8px' }}
                              separator={false}>
                          <Group
                              separator={false}>
                            <SimpleCell
                              separator={false}
                              before={
                                <img
                                  src={config.logoUrl}
                                  alt={server.name}
                                  style={{
                                    width: '32px',
                                    height: '32px',
                                    borderRadius: '8px',
                                    objectFit: 'cover',
                                    backgroundColor: 'var(--vkui--color_field_background)'
                                  }}
                                  onError={(e) => {
                                    // Если изображение не загрузилось, скрываем его
                                    e.target.style.display = 'none';
                                  }}
                                />
                              }
                              after={
                                <div style={{ display: 'flex', alignItems: 'center', gap: '6px', flexShrink: 0 }}>
                                  <Text weight="semibold" style={{ fontSize: '12px', whiteSpace: 'nowrap' }}>
                                    {onlineCount} / {maxPlayers}
                                  </Text>
                                  <div
                                    style={{
                                      width: '10px',
                                      height: '10px',
                                      borderRadius: '50%',
                                      background: isOnline ? statusColor : '#95a5a6',
                                      flexShrink: 0
                                    }}
                                  />
                                </div>
                              }
                              onClick={() => {
                                const connectText = `connect ${server.text_ip || server.ip}`;
                                
                                // Пробуем использовать VK Bridge для копирования
                                if (bridge) {
                                  bridge.send('VKWebAppCopyText', { text: connectText })
                                    .then(() => {
                                      setCopiedId(index);
                                      setTimeout(() => setCopiedId(null), 2000);
                                    })
                                    .catch(() => {
                                      // Fallback на обычное копирование
                                      copyToClipboardFallback(connectText, index);
                                    });
                                } else {
                                  copyToClipboardFallback(connectText, index);
                                }
                              }}
                              style={{ 
                                cursor: 'pointer',
                                transition: 'background-color 0.2s'
                              }}
                            >
                              <div style={{ display: 'flex', alignItems: 'center', gap: '8px', flex: 1, minWidth: 0 }}>
                                <Text weight="semibold" style={{ fontSize: '14px', whiteSpace: 'nowrap' }}>
                                  {server.name}
                                </Text>
                                <Text style={{ fontSize: '12px', color: 'var(--vkui--color_text_secondary)', whiteSpace: 'nowrap' }}>
                                  |
                                </Text>
                                <div style={{ display: 'flex', alignItems: 'center', gap: '4px', minWidth: 0 }}>
                                  <Text style={{ fontSize: '13px', whiteSpace: 'nowrap' }}>
                                    connect {server.text_ip || server.ip}
                                  </Text>
                                  {copiedId === index ? (
                                    <Text style={{ fontSize: '10px', color: 'var(--vkui--color_icon_accent)', whiteSpace: 'nowrap' }}>
                                      ✓
                                    </Text>
                                  ) : (
                                    <Icon16CopyOutline style={{ opacity: 0.5, flexShrink: 0 }} />
                                  )}
                                </div>
                              </div>
                            </SimpleCell>

                            {isOnline && maxPlayers > 0 && (
                              <div style={{ padding: '0 16px 12px' }}>
                                <div
                                  style={{
                                    height: '6px',
                                    background: 'var(--vkui--color_field_background)',
                                    borderRadius: '3px',
                                    overflow: 'hidden',
                                    position: 'relative'
                                  }}
                                >
                                  <div
                                    style={{
                                      width: `${fillPercentage}%`,
                                      height: '100%',
                                      background: statusColor,
                                      transition: 'width 0.3s ease'
                                    }}
                                  />
                                </div>
                                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginTop: '4px' }}>
                                  <Text
                                    style={{
                                      fontSize: '11px',
                                      color: 'var(--vkui--color_text_secondary)'
                                    }}
                                  >
                                    {fillPercentage}% заполнено
                                  </Text>
                                  {tags.length > 0 && (
                                    <Text
                                      style={{
                                        fontSize: '10px',
                                        color: 'var(--vkui--color_text_secondary)',
                                        marginLeft: '8px',
                                        textAlign: 'right'
                                      }}
                                    >
                                      {tags.join(', ')}
                                    </Text>
                                  )}
                                </div>
                              </div>
                            )}
                          </Group>
                        </Card>
                      </React.Fragment>
                    );
                  })}
                </div>
              )}

              {!loading && !error && servers.length === 0 && (
                <Group>
                  <Banner
                    header="Серверы не найдены"
                    subheader="В данный момент нет доступных серверов"
                  />
                </Group>
              )}
            </Panel>
          </View>
        </SplitCol>
      </SplitLayout>
    </AppRoot>
  );
}

export default App;

