'use client';

import toast from 'react-hot-toast';

// Функции-обёртки для toast
export const toastSuccess = (message: string) => {
  return toast.success(message, {
    style: {
      borderRadius: '16px',
      background: '#333',
      color: '#fff',
    },
  });
};

export const toastError = (message: string) => {
  return toast.error(message, {
    style: {
      borderRadius: '16px',
      background: '#333',
      color: '#fff',
    },
  });
};

export const toastWarning = (message: string) => {
  return toast(message, {
    icon: '⚠️',
    style: {
      borderRadius: '16px',
      background: '#333',
      color: '#fff',
    },
  });
};

export const toastInfo = (message: string) => {
  return toast(message, {
    icon: 'ℹ️',
    style: {
      borderRadius: '16px',
      background: '#333',
      color: '#fff',
    },
  });
};

// Экспортируем также стандартный toast для случаев, когда нужны дополнительные возможности
export { toast };
