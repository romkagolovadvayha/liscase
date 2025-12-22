import React from 'react';
import classNames from 'classnames';
import Icon from '@/components/icons/Icon';

interface BaseButtonProps {
  variant?: 'primary' | 'secondary' | 'tertiary';
  size?: 'small' | 'medium' | 'large';
  leftIcon?: string;
  rightIcon?: string;
  iconSize?: 'inherit' | 'small' | 'medium' | 'large';
  faIconSize?: 'xs' | 'sm' | 'lg' | 'xl' | '2x' | '1x' | '2xs';
  faIconFixedSize?: number; // Фиксированный размер в пикселях для Font Awesome
  children: React.ReactNode;
  className?: string;
  disabled?: boolean;
  loading?: boolean; // Состояние загрузки
}

interface ButtonProps extends BaseButtonProps, React.ButtonHTMLAttributes<HTMLButtonElement> {
  as?: 'button';
}

interface ButtonLinkProps extends BaseButtonProps, React.AnchorHTMLAttributes<HTMLAnchorElement> {
  as: 'a';
  href: string;
}

type ButtonComponentProps = ButtonProps | ButtonLinkProps;

export default function Button({
  variant = 'primary',
  size = 'medium',
  leftIcon,
  rightIcon,
  iconSize = 'medium',
  faIconSize = 'lg',
  faIconFixedSize,
  children,
  className,
  disabled,
  loading = false,
  as,
  ...props
}: ButtonComponentProps) {
  const baseClasses = classNames(
    'button',
    `button-${variant}`,
    size === 'small' && 'button-size__s',
    size === 'medium' && 'button-size__m',
    size === 'large' && 'button-size__l',
    {
      'button-disabled': disabled || loading,
      'button-loading': loading,
    },
    className
  );

  // Определяем фиксированный размер для Font Awesome иконок
  // Для Steam делаем крупнее
  const getFaFixedSize = (iconName: string) => {
    if (faIconFixedSize) return faIconFixedSize;
    if (iconName.toLowerCase() === 'steam') return 24; // Крупнее для Steam
    return 20; // Стандартный размер для других иконок
  };

  // Если loading, показываем иконку загрузки
  const displayLeftIcon = loading ? 'loading' : leftIcon;
  const displayRightIcon = loading ? undefined : rightIcon;

  const content = (
    <span className="button__text">
      {displayLeftIcon && (
        <Icon 
          name={displayLeftIcon} 
          fontSize={iconSize} 
          faSize={faIconSize}
          faFixedSize={getFaFixedSize(displayLeftIcon)}
          className={classNames(
            'button__icon',
            'button__icon--left',
            (displayLeftIcon === 'steam' || displayLeftIcon === 'Steam') && 'button__icon--steam',
            loading && 'button__icon--loading'
          )}
        />
      )}
      {!loading && children}
      {displayRightIcon && (
        <Icon 
          name={displayRightIcon} 
          fontSize={iconSize} 
          faSize={faIconSize}
          faFixedSize={getFaFixedSize(displayRightIcon)}
          className={classNames(
            'button__icon',
            'button__icon--right',
            (displayRightIcon === 'steam' || displayRightIcon === 'Steam') && 'button__icon--steam'
          )}
        />
      )}
    </span>
  );

  if (as === 'a') {
    const { href, ...linkProps } = props as ButtonLinkProps;
    return (
      <a
        href={href}
        className={baseClasses}
        {...(linkProps as React.AnchorHTMLAttributes<HTMLAnchorElement>)}
      >
        {content}
      </a>
    );
  }

  return (
    <button
      className={baseClasses}
      disabled={disabled || loading}
      {...(props as React.ButtonHTMLAttributes<HTMLButtonElement>)}
    >
      {content}
    </button>
  );
}

