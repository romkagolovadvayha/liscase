import { render, screen } from '../setup/test-utils';
import StoreClient from '@/components/store/StoreClient';

// Mock fetch for WebSocket token
global.fetch = jest.fn(() =>
  Promise.resolve({
    json: () => Promise.resolve({ token: 'test-token', steamId: 'test-steam-id' }),
  })
) as jest.Mock;

const mockInitialData = {
  items: [
    {
      id: 1,
      user_id: 1,
      drop_id: 1,
      box_id: null,
      sets_id: null,
      parent_drop_id: null,
      status: 'active' as const,
      count: 1,
      created_at: new Date().toISOString(),
      drop: {
        id: 1,
        name: 'Test Item',
        image: '/test.png',
        category_id: 1,
        price: 100,
      },
      category: {
        id: 1,
        name: 'Test Category',
      },
    },
  ],
  server: {
    id: 1,
    name: 'Test Server',
    tag: 'test',
    is_store: 1,
  },
  categories: [
    { id: 1, name: 'Test Category' },
  ],
  total: 1,
};

describe('StoreClient', () => {
  it('renders store page', () => {
    render(<StoreClient initialData={mockInitialData} />);
    expect(screen.getByText('Корзина сервера')).toBeInTheDocument();
  });

  it('displays items when available', () => {
    render(<StoreClient initialData={mockInitialData} />);
    expect(screen.getByText('Test Item')).toBeInTheDocument();
  });

  it('displays empty message when no items', () => {
    const emptyData = {
      ...mockInitialData,
      items: [],
      total: 0,
    };
    render(<StoreClient initialData={emptyData} />);
    expect(screen.getByText(/В вашем инвентаре пока нет вещей/)).toBeInTheDocument();
  });
});

