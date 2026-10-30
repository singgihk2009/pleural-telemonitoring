import { renderRouter, screen } from 'expo-router/testing-library';
import RootLayout from './src/app/_layout';
import Index from './src/app/index';

test('opens the development placeholder through the root route', async () => {
  await renderRouter({ _layout: RootLayout, index: Index }, { initialUrl: '/' });
  expect(screen.getByText('Development workspace')).toBeTruthy();
  expect(screen).toHavePathname('/');
});
