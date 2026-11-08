import { Stack } from 'expo-router';
import { StatusBar } from 'expo-status-bar';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { OfflineProvider } from '../patient/offline/Provider';
import { PatientProvider } from '../patient/session';

export default function RootLayout() {
  return (
    <SafeAreaProvider>
      <PatientProvider>
        <OfflineProvider>
        <StatusBar style="dark" />
        <Stack screenOptions={{ headerShown: false, animation: 'slide_from_right' }} />
        </OfflineProvider>
      </PatientProvider>
    </SafeAreaProvider>
  );
}
