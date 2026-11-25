// @vitest-environment jsdom
import {readFileSync} from 'node:fs'
import {URL as NodeURL} from 'node:url'
import {cleanup, render, screen} from '@testing-library/react'
import {afterEach, expect, test, vi} from 'vitest'
import {HealthItuesReview} from './HealthItuesReview'
import {ApiError, request} from './api'

vi.mock('./api', async () => ({...await vi.importActual('./api'), request:vi.fn()}))
const session = {token:'SYNTHETIC', expiresAt:'2099-01-01T00:00:00Z'}
afterEach(cleanup)

test('shows all 20 source-order statements with no collection controls', async () => {
  const fixture = JSON.parse(readFileSync(new NodeURL('../../api/resources/instruments/health-itues-v1.0.json', import.meta.url), 'utf8'))
  vi.mocked(request).mockResolvedValue({data:fixture})
  render(<HealthItuesReview session={session} onExpired={vi.fn()} />)
  await screen.findByText('S20')
  expect(screen.getAllByRole('listitem')).toHaveLength(20)
  expect(screen.getAllByRole('listitem')[0].textContent).toContain(fixture.subscales[0].items[0].statement)
  expect(screen.queryByRole('button')).toBeNull()
})

test('reports denied access without rendering statements', async () => {
  vi.mocked(request).mockRejectedValue(new ApiError(403))
  render(<HealthItuesReview session={session} onExpired={vi.fn()} />)
  expect(await screen.findByRole('alert')).toBeTruthy()
  expect(screen.queryByRole('list')).toBeNull()
})
