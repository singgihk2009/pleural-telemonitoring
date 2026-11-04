import {
  useWindowDimensions,
  Pressable,
  ScrollView,
  Text,
  View,
  type TextProps,
  type ViewStyle,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { router } from 'expo-router';
import type { PropsWithChildren } from 'react';
import { usePatient } from './context';
import { colors, styles } from './theme';
import { emergency } from './nativeActions';

export function Copy({ style, ...props }: TextProps) {
  return <Text {...props} allowFontScaling style={[styles.copy, style]} />;
}
export function Heading({ children }: PropsWithChildren) {
  return (
    <Copy accessibilityRole="header" style={styles.heading}>
      {children}
    </Copy>
  );
}
export function Card({ children, style }: PropsWithChildren<{ style?: ViewStyle }>) {
  return <View style={[styles.card, style]}>{children}</View>;
}
export function Button({
  title,
  onPress,
  disabled,
  secondary,
  danger,
  testID,
}: {
  title: string;
  onPress?: () => void;
  disabled?: boolean;
  secondary?: boolean;
  danger?: boolean;
  testID?: string;
}) {
  return (
    <Pressable
      testID={testID}
      accessibilityRole="button"
      accessibilityLabel={title}
      accessibilityState={{ disabled: !!disabled }}
      disabled={disabled}
      onPress={onPress}
      style={({ pressed }) => [
        styles.button,
        secondary && styles.secondary,
        danger && styles.danger,
        disabled && styles.disabled,
        pressed && { opacity: 0.75 },
      ]}
    >
      <Copy
        style={[
          styles.buttonLabel,
          secondary && { color: colors.blue },
          disabled && { color: '#4A586A' },
        ]}
      >
        {title}
      </Copy>
    </Pressable>
  );
}
export function Notice({ children }: PropsWithChildren) {
  return (
    <View style={styles.notice}>
      <Copy accessibilityLiveRegion="polite">{children}</Copy>
    </View>
  );
}
export function HelpBar() {
  return (
    <View style={styles.help}>
      <View style={styles.helpItem}>
        <Button title="Hubungi perawat" secondary onPress={() => router.push('/contact')} />
      </View>
      <View style={styles.helpItem}>
        <Button
          title="Darurat 119"
          danger
          onPress={() => {
            void emergency();
          }}
        />
      </View>
    </View>
  );
}
export function Frame({
  title,
  step,
  back = true,
  onBack,
  children,
}: PropsWithChildren<{ title: string; step?: string; back?: boolean; onBack?: () => void }>) {
  const patient = usePatient();
  const compact = useWindowDimensions().fontScale > 1.3;
  return (
    <SafeAreaView style={styles.safe} edges={['top', 'bottom']}>
      <View style={[styles.top, { paddingVertical: 12 }]}>
        <View style={styles.wordmark}>
          {!compact && (
            <View style={styles.mark}>
              <Copy style={styles.markText}>TN</Copy>
            </View>
          )}
          <View style={{ flex: 1 }}>
            <Copy style={styles.brand}>TeleNapas</Copy>
          </View>
        </View>
      </View>
      <View style={[styles.titleRow, compact && { paddingTop: 16, paddingBottom: 12 }]}>
        {back && (
          <Pressable
            accessibilityRole="button"
            accessibilityLabel="Kembali"
            onPress={onBack ?? (() => (router.canGoBack() ? router.back() : router.replace('/')))}
            style={styles.back}
          >
            <Copy style={{ color: colors.blue, fontSize: 28 }}>‹</Copy>
          </Pressable>
        )}
        <View style={{ flex: 1 }}>
          {step && !compact && <Copy style={styles.eyebrow}>{step.replace(/^F\d+ · /, '')}</Copy>}
          <Heading>{title}</Heading>
        </View>
      </View>
      <ScrollView
        key={title}
        style={{ flex: 1 }}
        contentContainerStyle={styles.content}
        keyboardShouldPersistTaps="handled"
        automaticallyAdjustKeyboardInsets
      >
        {patient.offline && (
          <Notice>
            Anda sedang offline. Tidak ada isian yang dikirim. Tombol darurat tetap tersedia.
          </Notice>
        )}
        {children}
      </ScrollView>
      <HelpBar />
    </SafeAreaView>
  );
}
