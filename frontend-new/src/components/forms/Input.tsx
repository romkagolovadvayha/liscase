import React from 'react';
import classNames from 'classnames';
import Icon from '@/components/icons/Icon';

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  hasError?: boolean;
  leftIcon?: string;
  rightIcon?: string;
  onRightIconClick?: () => void;
  rightIconTitle?: string;
  iconSize?: 'inherit' | 'small' | 'medium' | 'large';
  faIconSize?: 'xs' | 'sm' | 'lg' | 'xl' | '2x' | '1x' | '2xs';
  faIconFixedSize?: number;
}

export default function Input({ 
  hasError, 
  leftIcon, 
  rightIcon,
  onRightIconClick,
  rightIconTitle,
  iconSize = 'small',
  faIconSize = 'sm',
  faIconFixedSize = 20,
  className, 
  ...props 
}: InputProps) {
  const hasIcons = leftIcon || rightIcon;

  if (hasIcons) {
    return (
      <div className={classNames('input-wrapper', { 'input-wrapper--error': hasError })}>
        {leftIcon && (
          <span className="input-icon input-icon--left">
            <Icon 
              name={leftIcon} 
              fontSize={iconSize} 
              faSize={faIconSize}
              faFixedSize={faIconFixedSize}
            />
          </span>
        )}
        <input
          className={classNames(
            'form-control',
            {
              'form-control--with-left-icon': leftIcon,
              'form-control--with-right-icon': rightIcon,
              'border-border-color-active': hasError,
            },
            className
          )}
          {...props}
        />
        {rightIcon && (
          <span 
            className={classNames('input-icon input-icon--right', {
              'input-icon--clickable': !!onRightIconClick
            })}
            onClick={onRightIconClick}
            title={rightIconTitle}
          >
            <Icon 
              name={rightIcon} 
              fontSize={iconSize} 
              faSize={faIconSize}
              faFixedSize={faIconFixedSize}
            />
          </span>
        )}
      </div>
    );
  }

  return (
    <input
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

