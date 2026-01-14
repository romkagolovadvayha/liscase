# Testing Guide

This directory contains all tests for the application.

## Structure

```
__tests__/
├── setup/              # Test setup utilities
│   ├── test-utils.tsx  # Custom render function with providers
│   └── mocks/          # MSW mocks (optional)
│       ├── handlers.ts # API mock handlers
│       └── server.ts   # MSW server setup
└── [feature]/          # Feature-specific tests
```

## Running Tests

```bash
# Run all tests
npm test

# Run tests in watch mode
npm run test:watch

# Run tests with coverage
npm run test:coverage
```

## Writing Tests

### Basic Component Test

```tsx
import { render, screen } from '@/__tests__/setup/test-utils'
import MyComponent from '@/components/MyComponent'

describe('MyComponent', () => {
  it('renders correctly', () => {
    render(<MyComponent />)
    expect(screen.getByText('Hello')).toBeInTheDocument()
  })
})
```

### Test with MSW (API Mocking)

For tests that need to mock API calls:

```tsx
import { render, screen, setupMSW } from '@/__tests__/setup/test-utils'
import { http, HttpResponse } from 'msw'

describe('Component with API', () => {
  let server: any

  beforeAll(async () => {
    server = await setupMSW()
    server.use(
      http.get('/api/data', () => {
        return HttpResponse.json({ data: 'test' })
      })
    )
    server.listen({ onUnhandledRequest: 'error' })
  })

  afterEach(() => server.resetHandlers())
  afterAll(() => server.close())

  it('fetches and displays data', async () => {
    render(<MyComponent />)
    // ... test implementation
  })
})
```

## Test Utilities

### `test-utils.tsx`

Provides:
- `render()` - Custom render function with all providers (QueryClient, Antd, etc.)
- `setupMSW()` - Helper to set up MSW with required polyfills

### Providers Included

- React Query (QueryClientProvider)
- Ant Design (ConfigProvider)
- FontAwesome (FontAwesomeProvider)
- Toast (ToastProvider)
- Tooltip (TooltipProvider)

## Coverage Requirements

Minimum coverage: 80% for:
- Branches
- Functions
- Lines
- Statements







