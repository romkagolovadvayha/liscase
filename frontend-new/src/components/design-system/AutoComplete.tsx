'use client';

import React, { useState, useRef, useEffect } from 'react';
import classNames from 'classnames';
import { AutoComplete as AntAutoComplete } from 'antd';
import type { AutoCompleteProps as AntAutoCompleteProps } from 'antd';
import { SearchOutlined } from '@mui/icons-material';
import '@/styles/design-system/autocomplete.scss';

export interface AutoCompleteOption {
  value: string;
  label: React.ReactNode;
  avatar?: string;
  username?: string;
  steam_id?: string;
  statsLink?: string;
  status?: boolean;
  [key: string]: any;
}

export interface AutoCompleteProps extends Omit<AntAutoCompleteProps, 'options' | 'onSearch' | 'onSelect'> {
  options?: AutoCompleteOption[];
  onSearch?: (value: string) => void;
  onSelect?: (value: string, option: AutoCompleteOption) => void;
  placeholder?: string;
  className?: string;
  showOnlineStatus?: boolean;
  showIcon?: boolean; // Показывать ли иконку поиска
}

export default function AutoComplete({
  options = [],
  onSearch,
  onSelect,
  placeholder = 'Введите текст...',
  className,
  showOnlineStatus = false,
  value,
  onChange,
  disabled = false,
  showIcon = true, // По умолчанию показываем иконку
  ...props
}: AutoCompleteProps) {
  const [isOpen, setIsOpen] = useState(false);
  const containerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  const handleSearch = (searchValue: string) => {
    setIsOpen(searchValue.length > 0 && options.length > 0);
    onSearch?.(searchValue);
  };

  const handleSelect = (selectedValue: string, option: any) => {
    setIsOpen(false);
    // Находим полную опцию из options
    const fullOption = options.find((opt) => opt.value === selectedValue) || option;
    onSelect?.(selectedValue, fullOption);
  };

  const handleFocus = () => {
    if (value && String(value).length > 0 && options.length > 0) {
      setIsOpen(true);
    }
  };

  const handleBlur = () => {
    // Задержка, чтобы клик по опции успел обработаться
    setTimeout(() => setIsOpen(false), 200);
  };

  useEffect(() => {
    // Открываем dropdown если есть опции после загрузки
    if (value && String(value).length > 0 && options.length > 0) {
      setIsOpen(true);
    } else if (options.length === 0) {
      setIsOpen(false);
    }
  }, [options, value]);

  return (
    <div 
      ref={containerRef}
      className={classNames('ds-autocomplete', className, {
        'ds-autocomplete--no-icon': !showIcon,
      })}
    >
      <div className="ds-autocomplete__wrapper">
        {showIcon && <SearchOutlined className="ds-autocomplete__icon" />}
        <AntAutoComplete
          {...props}
          value={value}
          onChange={(val) => {
            if (onChange) {
              onChange(val);
            }
            if (!disabled) {
              handleSearch(String(val || ''));
            }
          }}
          options={options.map((option) => ({
            value: option.value,
            label: option.label,
          }))}
          onSearch={handleSearch}
          onSelect={handleSelect}
          onFocus={handleFocus}
          onBlur={handleBlur}
          open={isOpen && !disabled}
          placeholder={placeholder}
          notFoundContent={null}
          className="ds-autocomplete__input"
          popupClassName="ds-autocomplete__dropdown"
          suffixIcon={null}
          disabled={disabled}
        />
      </div>
    </div>
  );
}

