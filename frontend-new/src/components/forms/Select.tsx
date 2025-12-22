import React from 'react';
import classNames from 'classnames';

interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
  hasError?: boolean;
}

export default function Select({ hasError, className, children, ...props }: SelectProps) {
  return (
    <select
      className={classNames(
        'select',
        {
          'border-border-color-active': hasError,
        },
        className
      )}
      {...props}
    >
      {children}
    </select>
  );
}











