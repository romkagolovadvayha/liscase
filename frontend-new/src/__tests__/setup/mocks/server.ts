// MSW server setup - will be configured when needed in individual test files
// This file exports a function to create the server, avoiding import-time issues

// Note: MSW requires Response API polyfills which should be set up before using this
// Use setupMSW() helper function in test files that need MSW

export function createMSWServer() {
  // Dynamic import to avoid issues with Response API during module load
  const { setupServer } = require('msw/node')
  const { handlers } = require('./handlers')
  return setupServer(...handlers)
}

// For backward compatibility, export a lazy server
let _server: any = null

export function getServer() {
  if (!_server) {
    _server = createMSWServer()
  }
  return _server
}

// Export server for direct use (will be created on first access)
export const server = new Proxy({} as any, {
  get(target, prop) {
    const s = getServer()
    return s[prop]
  },
})
