import { act, create, type ReactTestRenderer } from 'react-test-renderer';
import App from './App';

test('renders the development placeholder', async () => {
  let tree!: ReactTestRenderer;
  await act(async () => { tree = create(<App />); });
  expect(JSON.stringify(tree.toJSON())).toContain('Development workspace');
  await act(async () => { tree.unmount(); });
});
