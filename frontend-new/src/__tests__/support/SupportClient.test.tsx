import { render, screen } from '../setup/test-utils';
import SupportClient from '@/components/support/SupportClient';

const mockInitialData = {
  user: {
    id: 1,
    username: 'testuser',
    avatar: '/avatar.png',
    blocked_support: false,
    blocked_support_at: null,
    isAdmin: false,
  },
  tickets: [],
  activeTicket: null,
};

describe('SupportClient', () => {
  it('renders support page', () => {
    render(<SupportClient initialData={mockInitialData} />);
    expect(screen.getByText('Поддержка')).toBeInTheDocument();
  });

  it('renders ticket list when tickets exist', () => {
    const dataWithTickets = {
      ...mockInitialData,
      tickets: [
        {
          id: 1,
          number: 43243,
          user_id: 1,
          server_tag: null,
          status: 'open' as const,
          created_at: new Date().toISOString(),
          updated_at: new Date().toISOString(),
          user: {
            id: 1,
            username: 'testuser',
            avatar: '/avatar.png',
            blocked_support: false,
            blocked_support_at: null,
          },
        },
      ],
    };

    render(<SupportClient initialData={dataWithTickets} />);
    expect(screen.getByText('#43243')).toBeInTheDocument();
  });
});







