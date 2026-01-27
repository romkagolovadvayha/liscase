import { render, screen } from './setup/test-utils'

describe('Example Test', () => {
  it('should render a simple component', () => {
    render(<div>Hello World</div>)
    expect(screen.getByText('Hello World')).toBeInTheDocument()
  })
})







