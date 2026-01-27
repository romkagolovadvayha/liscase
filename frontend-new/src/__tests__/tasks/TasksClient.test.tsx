import { render, screen } from '../setup/test-utils';
import TasksClient from '@/components/tasks/TasksClient';

const mockInitialData = {
  tasks: [
    {
      id: 1,
      name: 'Test Task',
      description: 'Test description',
      type: 'one_time' as const,
      check_type: 'manual' as const,
      reward_type: 'currency' as const,
      reward_amount: 100,
      is_active: true,
      is_vip: false,
      sort: 1,
      global_completed: 10,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
      user_status: 'available' as const,
      completion_count: 0,
    },
  ],
  stats: {
    total_tasks: 1,
    completed_tasks: 0,
    total_rewards: 0,
    total_coins: 0,
    total_potential_coins: 100,
    total_potential_rewards: 1,
  },
};

// Mock fetch
global.fetch = jest.fn(() =>
  Promise.resolve({
    json: () => Promise.resolve({ success: true }),
  })
) as jest.Mock;

describe('TasksClient', () => {
  it('renders tasks page', () => {
    render(<TasksClient initialData={mockInitialData} />);
    expect(screen.getByText('Задания')).toBeInTheDocument();
  });

  it('displays tasks', () => {
    render(<TasksClient initialData={mockInitialData} />);
    expect(screen.getByText('Test Task')).toBeInTheDocument();
  });
});







