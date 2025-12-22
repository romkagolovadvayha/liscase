import React from 'react';
import classNames from 'classnames';

interface CheckboxProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
}

export default function Checkbox({ label, className, id, ...props }: CheckboxProps) {
  const checkboxId = id || `checkbox-${Math.random().toString(36).substr(2, 9)}`;

  return (
    <div className={classNames('flex items-center gap-x-12 cursor-pointer', className)}>
      <input
        type="checkbox"
        id={checkboxId}
        className="form-check-input"
        {...props}
      />
      {label && (
        <label htmlFor={checkboxId} className="cursor-pointer user-select-none">
          {label}
        </label>
      )}
    </div>
  );
}











