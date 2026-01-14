import React from 'react';
import classNames from 'classnames';

interface SwitchProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label?: string;
}

export default function Switch({ label, className, id, checked, onChange, disabled, ...props }: SwitchProps) {
  const switchId = id || `switch-${Math.random().toString(36).substr(2, 9)}`;

  return (
    <div className={classNames('switch-wrapper', className)}>
      <label htmlFor={switchId} className={classNames('switch', { 'switch--disabled': disabled })}>
        <input
          type="checkbox"
          id={switchId}
          className="switch__input"
          checked={checked}
          onChange={onChange}
          disabled={disabled}
          {...props}
        />
        <span className="switch__slider" />
      </label>
      {label && (
        <label htmlFor={switchId} className="switch__label">
          {label}
        </label>
      )}
    </div>
  );
}



















