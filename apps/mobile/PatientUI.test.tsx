import { render, screen, fireEvent } from '@testing-library/react-native';
import { VasControl, SymptomForm } from './src/patient/screens';
import { studyDay } from './src/patient/api';

test('VAS begins empty and only accepts an explicit user selection, including zero', async () => {
  const change = jest.fn();
  await render(<VasControl value={null} onChange={change} />);
  expect(screen.getByText('— — mm')).toBeTruthy();
  expect(change).not.toHaveBeenCalled();
  await fireEvent(screen.getByTestId('vas-touch-area'), 'layout', {
    nativeEvent: { layout: { width: 240 } },
  });
  await fireEvent(screen.getByTestId('vas-touch-area'), 'responderGrant', {
    nativeEvent: { locationX: 0 },
  });
  expect(change).toHaveBeenCalledWith(0);
});
test('integer VAS control is neutral and allows 100 without clinical classification', async () => {
  await render(<VasControl value={100} onChange={jest.fn()} />);
  expect(screen.getByText('100 mm')).toBeTruthy();
  expect(screen.getByRole('adjustable')).toHaveProp('accessibilityValue', {
    min: 0,
    max: 100,
    now: 100,
    text: '100 milimeter',
  });
  expect(screen.queryByText(/MERAH|KUNING|HIJAU/)).toBeNull();
});
test('wireframe symptoms are labelled illustrative and cannot submit', async () => {
  await render(<SymptomForm />);
  expect(screen.getByText(/bukan daftar gejala final/)).toBeTruthy();
  expect(screen.getByRole('button', { name: 'Kirim · belum tersedia' })).toBeDisabled();
  for (const option of screen.getAllByRole('button', { name: 'Ya' })) expect(option).toBeDisabled();
});
test('WIB day calculation handles midnight, future discharge and bad values', () => {
  expect(studyDay('2026-11-01', new Date('2026-11-01T16:59:59Z'))).toBe(0);
  expect(studyDay('2026-11-01', new Date('2026-11-01T17:00:00Z'))).toBe(1);
  expect(studyDay('2026-11-02', new Date('2026-11-01T00:00:00Z'))).toBe(-1);
  expect(studyDay('2026-02-30')).toBeNull();
  expect(studyDay('2026-11-01T00:00:00.000000Z', new Date('2026-11-01T17:00:00Z'))).toBe(1);
  expect(studyDay('invalid')).toBeNull();
});

test('text and actionable control styles meet the minimum scalable sizes', async () => {
  const { styles } = require('./src/patient/theme');
  const { StyleSheet } = require('react-native');
  expect(StyleSheet.flatten(styles.copy).fontSize).toBeGreaterThanOrEqual(16);
  expect(StyleSheet.flatten(styles.button).minHeight).toBeGreaterThanOrEqual(48);
  expect(StyleSheet.flatten(styles.back).minHeight).toBeGreaterThanOrEqual(48);
  expect(StyleSheet.flatten(styles.input).minHeight).toBeGreaterThanOrEqual(48);
  expect(StyleSheet.flatten(styles.helpItem).minWidth).toBeGreaterThanOrEqual(48);
});

test('119 opens the dialer only after explicit confirmation', async () => {
  const { Alert, Linking } = require('react-native');
  const { emergency } = require('./src/patient/nativeActions');
  const prompt = jest.spyOn(Alert, 'alert').mockImplementation(() => {});
  const dialer = jest.spyOn(Linking, 'openURL').mockResolvedValue(undefined);
  await emergency();
  expect(dialer).not.toHaveBeenCalled();
  const buttons = prompt.mock.calls[0][2] as { onPress?: () => void }[];
  buttons[1].onPress?.();
  expect(dialer).toHaveBeenCalledWith('tel:119');
  prompt.mockRestore();
  dialer.mockRestore();
});

test('VAS target clamps touch input and exposes explicit one-mm accessibility actions', async () => {
  const change = jest.fn();
  await render(<VasControl value={null} onChange={change} />);
  const target = screen.getByRole('adjustable');
  expect(target).toHaveProp('accessibilityValue', { text: 'Belum dipilih' });
  await fireEvent(target, 'layout', { nativeEvent: { layout: { width: 240 } } });
  await fireEvent(target, 'responderGrant', { nativeEvent: { locationX: 300 } });
  expect(change).toHaveBeenLastCalledWith(100);
  await fireEvent(target, 'responderMove', { nativeEvent: { locationX: -20 } });
  expect(change).toHaveBeenLastCalledWith(0);
  await fireEvent(target, 'accessibilityAction', { nativeEvent: { actionName: 'increment' } });
  expect(change).toHaveBeenLastCalledWith(0);
});
