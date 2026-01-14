'use client';

import React from 'react';
import Link from 'next/link';
import type { Deposit } from '@/types/payment';

interface PaymentResultClientProps {
  deposit?: Deposit | null;
}

export default function PaymentResultClient({ deposit }: PaymentResultClientProps) {
  if (!deposit) {
    return (
      <div className="payment-result-page">
        <div className="payment-result-container">
          <div className="payment-result-message">
            <h1>Результат платежа</h1>
            <p>Информация о платеже не найдена</p>
            <Link href="/" className="button button-primary">
              Вернуться на главную
            </Link>
          </div>
        </div>
      </div>
    );
  }

  const isSuccess = deposit.status === 'success';
  const isFailed = deposit.status === 'failed';
  const isWaiting = deposit.status === 'wait_confirm';

  return (
    <div className="payment-result-page">
      <div className="payment-result-container">
        <div className={`payment-result-message payment-result-message--${deposit.status}`}>
          {isSuccess && (
            <>
              <div className="payment-result-icon">✅</div>
              <h1>Платеж успешно выполнен</h1>
              <p>Сумма: {deposit.amount} руб.</p>
              <p>Баланс пополнен</p>
            </>
          )}
          {isFailed && (
            <>
              <div className="payment-result-icon">❌</div>
              <h1>Платеж не выполнен</h1>
              <p>Попробуйте еще раз</p>
            </>
          )}
          {isWaiting && (
            <>
              <div className="payment-result-icon">⏳</div>
              <h1>Платеж обрабатывается</h1>
              <p>Ожидайте подтверждения</p>
            </>
          )}
          <div className="payment-result-actions">
            <Link href="/" className="button button-primary">
              Вернуться на главную
            </Link>
          </div>
        </div>
      </div>
    </div>
  );
}







