import React, { ReactElement } from 'react'
import { render, RenderOptions } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { ConfigProvider } from 'antd'
import FontAwesomeProvider from '@/components/providers/FontAwesomeProvider'
import ToastProvider from '@/components/providers/ToastProvider'
import TooltipProvider from '@/components/providers/TooltipProvider'

// Create a test query client
const createTestQueryClient = () =>
  new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
        cacheTime: 0,
      },
    },
  })

interface AllTheProvidersProps {
  children: React.ReactNode
}

const AllTheProviders = ({ children }: AllTheProvidersProps) => {
  const queryClient = createTestQueryClient()

  return (
    <ConfigProvider>
      <QueryClientProvider client={queryClient}>
        <FontAwesomeProvider>
          <TooltipProvider>
            <ToastProvider>{children}</ToastProvider>
          </TooltipProvider>
        </FontAwesomeProvider>
      </QueryClientProvider>
    </ConfigProvider>
  )
}

const customRender = (
  ui: ReactElement,
  options?: Omit<RenderOptions, 'wrapper'>
) => render(ui, { wrapper: AllTheProviders, ...options })

// Re-export everything
export * from '@testing-library/react'
export { customRender as render }

// Helper to setup MSW in tests that need it
export async function setupMSW() {
  const { TextEncoder, TextDecoder } = await import('util')
  if (typeof global.TextEncoder === 'undefined') {
    global.TextEncoder = TextEncoder
    global.TextDecoder = TextDecoder
  }

  const { fetch, Request, Response, Headers } = await import('undici')
  if (typeof global.fetch === 'undefined') {
    global.fetch = fetch as any
    global.Request = Request as any
    global.Response = Response as any
    global.Headers = Headers as any
  }

  const { getServer } = await import('./mocks/server')
  return getServer()
}
