import React from 'react';
import classNames from 'classnames';

interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  hasError?: boolean;
}

export default function Textarea({ hasError, className, ...props }: TextareaProps) {
  return (
    <textarea
      className={classNames(
        'form-control',
        {
          'border-border-color-active': hasError,
        },
        className
      )}
      {...props}
    />
  );
}



















