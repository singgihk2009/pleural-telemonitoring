import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { PatientProvider } from '../patient/session';

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <PatientProvider>
        <StatusBar style="dark" />
        <Stack screenOptions={{ headerShown: false, animation: 'slide_from_right' }} />
      </PatientProvider>
    </SafeAreaProvider>
  );
}
