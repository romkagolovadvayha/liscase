'use client';

import React, { useState } from 'react';
import Input from '@/components/forms/Input';

interface SearchProps {
  placeholder?: string;
  onSearch?: (value: string) => void;
}

export default function Search({ placeholder = 'Введите название предмета..', onSearch }: SearchProps) {
  const [value, setValue] = useState('');

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const newValue = e.target.value;
    setValue(newValue);
    if (onSearch) {
      onSearch(newValue);
    }
  };

  return (
    <Input
      type="text"
      className="search"
      id="search"
      placeholder={placeholder}
      value={value}
      onChange={handleChange}
      autoComplete="off"
      leftIcon="search"
      iconSize="small"
      faIconSize="sm"
    />
  );
}

