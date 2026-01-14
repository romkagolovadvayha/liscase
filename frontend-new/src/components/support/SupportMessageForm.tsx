'use client';

import React, { useState, useRef, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { AttachFile, Send, EmojiEmotions, Close } from '@mui/icons-material';
import apiClient from '@/lib/api/client';
import type { SupportSticker } from '@/types/support';

const messageSchema = z.object({
  message: z.string().optional(),
});

type MessageFormData = z.infer<typeof messageSchema>;

interface SupportMessageFormProps {
  onSend: (message: string, files?: File[]) => Promise<void>;
  disabled?: boolean;
  ticketId: number;
  onTyping?: (typing: boolean) => void;
  editingMessageId?: number | null;
  editingMessageText?: string;
  onCancelEdit?: () => void;
}

export default function SupportMessageForm({
  onSend,
  disabled = false,
  ticketId,
  onTyping,
  editingMessageId = null,
  editingMessageText = '',
  onCancelEdit,
}: SupportMessageFormProps) {
  const [isSending, setIsSending] = useState(false);
  const [selectedFiles, setSelectedFiles] = useState<File[]>([]);
  const [stickers, setStickers] = useState<SupportSticker[]>([]);
  const [showStickerPanel, setShowStickerPanel] = useState(false);
  const [loadingStickers, setLoadingStickers] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);
  const stickerPanelRef = useRef<HTMLDivElement>(null);
  const stickerButtonRef = useRef<HTMLButtonElement>(null);
  const typingTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const lastTypingStateRef = useRef<boolean>(false);
  const {
    register,
    handleSubmit,
    reset,
    watch,
    setValue,
    formState: { errors },
  } = useForm<MessageFormData>({
    resolver: zodResolver(messageSchema),
  });

  const messageValue = watch('message') || '';

  // Устанавливаем текст редактирования при изменении editingMessageText
  useEffect(() => {
    if (editingMessageId !== null && editingMessageText) {
      setValue('message', editingMessageText);
    } else if (editingMessageId === null) {
      setValue('message', '');
    }
  }, [editingMessageId, editingMessageText, setValue]);

  // Загрузка стикеров
  useEffect(() => {
    const loadStickers = async () => {
      setLoadingStickers(true);
      try {
        const response = await apiClient.get('/support/stickers');
        if (response.data.success && response.data.data?.stickers) {
          setStickers(response.data.data.stickers);
        }
      } catch (error) {
        console.error('Error loading stickers:', error);
      } finally {
        setLoadingStickers(false);
      }
    };

    loadStickers();
  }, []);

  // Закрытие панели стикеров при клике вне её
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (
        stickerPanelRef.current &&
        stickerButtonRef.current &&
        !stickerPanelRef.current.contains(event.target as Node) &&
        !stickerButtonRef.current.contains(event.target as Node)
      ) {
        setShowStickerPanel(false);
      }
    };

    if (showStickerPanel) {
      document.addEventListener('mousedown', handleClickOutside);
      return () => {
        document.removeEventListener('mousedown', handleClickOutside);
      };
    }
  }, [showStickerPanel]);

  // Автоматическое изменение высоты textarea
  useEffect(() => {
    const textarea = textareaRef.current;
    if (textarea) {
      // Сбрасываем высоту для правильного расчета
      textarea.style.height = '40px';
      // Устанавливаем высоту на основе содержимого
      const scrollHeight = textarea.scrollHeight;
      const lineHeight = parseInt(getComputedStyle(textarea).lineHeight);
      const minHeight = 40; // Минимум 40px (как кнопка отправки)
      const maxHeight = lineHeight * 5; // Максимум 5 строк
      textarea.style.height = `${Math.min(Math.max(scrollHeight, minHeight), maxHeight)}px`;
    }
  }, [messageValue]);

  // Отправка typing индикатора при изменении текста
  useEffect(() => {
    if (!onTyping) return;

    const isTyping = messageValue.trim().length > 0;

    // Если состояние typing изменилось, отправляем индикатор
    if (isTyping !== lastTypingStateRef.current) {
      lastTypingStateRef.current = isTyping;
      onTyping(isTyping);
    }

    // Очищаем предыдущий timeout
    if (typingTimeoutRef.current) {
      clearTimeout(typingTimeoutRef.current);
    }

    // Если пользователь печатает, отправляем typing = true сразу
    // и планируем отправку typing = false через 3 секунды бездействия
    if (isTyping) {
      typingTimeoutRef.current = setTimeout(() => {
        if (lastTypingStateRef.current) {
          lastTypingStateRef.current = false;
          onTyping(false);
        }
      }, 3000);
    }

    // Очистка при размонтировании
    return () => {
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
      }
    };
  }, [messageValue, onTyping]);

  const handleFileSelect = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files || []);
    setSelectedFiles((prev) => [...prev, ...files]);
  };

  const removeFile = (index: number) => {
    setSelectedFiles((prev) => prev.filter((_, i) => i !== index));
  };

  const handleStickerClick = async (sticker: SupportSticker) => {
    if (disabled || isSending) return;

    // Формируем HTML-тег для стикера
    let stickerHtml = '';
    if (sticker.type === 'image') {
      const width = sticker.width ? ` width="${sticker.width}"` : '';
      const height = sticker.height ? ` height="${sticker.height}"` : '';
      stickerHtml = `<img src="${sticker.url}" class="support_sticker"${width}${height} alt="${sticker.name}" />`;
    } else {
      const width = sticker.width ? ` width="${sticker.width}"` : '';
      const height = sticker.height ? ` height="${sticker.height}"` : '';
      stickerHtml = `<video src="${sticker.url}" class="support_sticker"${width}${height} autoplay loop muted></video>`;
    }

    // Закрываем панель стикеров
    setShowStickerPanel(false);

    // Отправляем стикер сразу
    setIsSending(true);
    try {
      await onSend(stickerHtml, []);
    } catch (error) {
      console.error('Error sending sticker:', error);
    } finally {
      setIsSending(false);
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent<HTMLTextAreaElement>) => {
    // Отправка по Enter (без Shift)
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit(onSubmit)();
    }
  };

  const onSubmit = async (data: MessageFormData) => {
    if (disabled || isSending) return;

    // В режиме редактирования проверяем только наличие текста
    if (editingMessageId !== null) {
      if (!data.message?.trim()) {
        return;
      }
    } else {
      // Проверяем, есть ли стикеры в сообщении (тег <img class="support_sticker")
      const hasStickers = data.message && (
        data.message.includes('class="support_sticker"') || 
        data.message.includes("class='support_sticker'")
      );
      
      // При отправке нового сообщения проверяем, что есть либо сообщение (включая стикеры), либо файлы
      if (!data.message?.trim() && !hasStickers && selectedFiles.length === 0) {
        return;
      }
    }

    // Останавливаем typing индикатор перед отправкой
    if (onTyping && lastTypingStateRef.current) {
      lastTypingStateRef.current = false;
      onTyping(false);
      if (typingTimeoutRef.current) {
        clearTimeout(typingTimeoutRef.current);
        typingTimeoutRef.current = null;
      }
    }

    setIsSending(true);
    try {
      await onSend(data.message || '', editingMessageId !== null ? [] : selectedFiles);
      reset();
      setSelectedFiles([]);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
      // Сбрасываем высоту textarea
      if (textareaRef.current) {
        textareaRef.current.style.height = '40px';
      }
    } catch (error) {
      console.error('Error sending message:', error);
    } finally {
      setIsSending(false);
    }
  };

  return (
    <div className="support-message-form">
      {editingMessageId !== null && (
        <div className="support-message-form-edit-notice">
          <span>Редактирование сообщения</span>
          {onCancelEdit && (
            <button
              type="button"
              onClick={onCancelEdit}
              className="support-message-form-cancel-edit"
              title="Отменить редактирование"
            >
              <Close />
            </button>
          )}
        </div>
      )}
      {selectedFiles.length > 0 && editingMessageId === null && (
        <div className="support-message-form-files">
          {selectedFiles.map((file, index) => (
            <div key={index} className="support-message-form-file-item">
              <span>{file.name}</span>
              <button
                type="button"
                onClick={() => removeFile(index)}
                className="support-message-form-file-remove"
              >
                ×
              </button>
            </div>
          ))}
        </div>
      )}
      <form onSubmit={handleSubmit(onSubmit)} className="support-message-form-wrapper">
        <div className="support-message-form-left">
          {editingMessageId === null && (
            <label className="support-message-form-file-label">
              <input
                ref={fileInputRef}
                type="file"
                className="support-message-form-file-input"
                accept=".png,.jpg,.jpeg,.gif,.webp,.bmp,.svg,.mp4,.avi,.mov,.webm,.ogg,.zip,.rar,.7z,.tar,.gz,.map,.json,.txt"
                onChange={handleFileSelect}
                disabled={disabled || isSending}
                multiple
              />
              <AttachFile />
            </label>
          )}
          {editingMessageId === null && (
            <div style={{ position: 'relative' }}>
              <button
                ref={stickerButtonRef}
                type="button"
                className="support-message-form-sticker-button"
                title="Стикеры"
                onClick={() => setShowStickerPanel(!showStickerPanel)}
                disabled={disabled || isSending}
              >
                <EmojiEmotions />
              </button>
            {showStickerPanel && (
              <div
                ref={stickerPanelRef}
                className="support-message-form-sticker-panel"
              >
                {loadingStickers ? (
                  <div className="support-message-form-sticker-panel-loading">
                    Загрузка стикеров...
                  </div>
                ) : stickers.length === 0 ? (
                  <div className="support-message-form-sticker-panel-empty">
                    Нет доступных стикеров
                  </div>
                ) : (
                  <div className="support-message-form-sticker-panel-grid">
                    {stickers.map((sticker) => (
                      <button
                        key={sticker.id}
                        type="button"
                        className="support-message-form-sticker-item"
                        onClick={() => handleStickerClick(sticker)}
                        title={sticker.name}
                      >
                        {sticker.type === 'image' ? (
                          <img src={sticker.url} alt={sticker.name} />
                        ) : (
                          <video src={sticker.url} muted loop />
                        )}
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}
            </div>
          )}
        </div>
        <div className="support-message-form-input">
          <textarea
            {...register('message')}
            ref={(e) => {
              const { ref } = register('message');
              if (typeof ref === 'function') {
                ref(e);
              }
              textareaRef.current = e;
            }}
            placeholder={editingMessageId !== null ? "Редактируйте сообщение..." : "Напишите сообщение..."}
            disabled={disabled || isSending}
            rows={1}
            onKeyDown={handleKeyDown}
          />
          {errors.message && (
            <span className="support-message-form-error">
              {errors.message.message}
            </span>
          )}
        </div>
        <button
          type="submit"
          disabled={disabled || isSending}
          className="support-message-form-send"
          title="Отправить (Enter)"
        >
          <Send />
        </button>
      </form>
    </div>
  );
}
