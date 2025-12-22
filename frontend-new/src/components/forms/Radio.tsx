import React from 'react';
import classNames from 'classnames';

interface RadioProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
}

export default function Radio({ label, className, id, ...props }: RadioProps) {
  const radioId = id || `radio-${Math.random().toString(36).substr(2, 9)}`;

  return (
    <div className={classNames('flex items-center gap-x-12 cursor-pointer', className)}>
      <input
        type="radio"
        id={radioId}
        className="form-check-input"
        {...props}
      />
      {label && (
        <label htmlFor={radioId} className="cursor-pointer user-select-none">
          {label}
        </label>
      )}
    </div>
  );
}











