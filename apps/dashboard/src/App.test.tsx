// @vitest-environment jsdom
import { render, screen, cleanup } from '@testing-library/react'
import { afterEach, expect, test } from 'vitest'
import App from './App'

afterEach(cleanup)
test('renders the development placeholder', () => {
  render(<App />)
  expect(screen.getByRole('heading', { name: 'Pleural Telemonitoring' })).toBeTruthy()
  expect(screen.getByText('Development workspace')).toBeTruthy()
})
