import React from 'react';
import classNames from 'classnames';

interface FormGroupProps {
  label?: string;
  error?: string;
  hint?: string;
  children: React.ReactNode;
  className?: string;
}

export default function FormGroup({ label, error, hint, children, className }: FormGroupProps) {
  return (
    <div className={classNames('form-group', className)}>
      {label && (
        <label className="control-label">
          {label}
        </label>
      )}
      {children}
      {error && (
        <div className="help-block">
          {error}
        </div>
      )}
      {hint && !error && (
        <div className="help-block text-text-secondary">
          {hint}
        </div>
      )}
    </div>
  );
}











