import { render, screen } from '../setup/test-utils';
import PaymentResultClient from '@/components/payment/PaymentResultClient';

describe('PaymentResultClient', () => {
  it('renders success message', () => {
    const deposit = {
      id: 1,
      user_id: 1,
      amount: 1000,
      payment_id: 'test-payment-id',
      payment_provider: 'tinkoff' as const,
      status: 'success' as const,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    render(<PaymentResultClient deposit={deposit} />);
    expect(screen.getByText('Платеж успешно выполнен')).toBeInTheDocument();
    expect(screen.getByText(/1000/)).toBeInTheDocument();
  });

  it('renders failed message', () => {
    const deposit = {
      id: 1,
      user_id: 1,
      amount: 1000,
      payment_id: 'test-payment-id',
      payment_provider: 'tinkoff' as const,
      status: 'failed' as const,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    render(<PaymentResultClient deposit={deposit} />);
    expect(screen.getByText('Платеж не выполнен')).toBeInTheDocument();
  });

  it('renders waiting message', () => {
    const deposit = {
      id: 1,
      user_id: 1,
      amount: 1000,
      payment_id: 'test-payment-id',
      payment_provider: 'tinkoff' as const,
      status: 'wait_confirm' as const,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };

    render(<PaymentResultClient deposit={deposit} />);
    expect(screen.getByText('Платеж обрабатывается')).toBeInTheDocument();
  });

  it('renders no deposit message', () => {
    render(<PaymentResultClient deposit={null} />);
    expect(screen.getByText('Информация о платеже не найдена')).toBeInTheDocument();
  });
});







