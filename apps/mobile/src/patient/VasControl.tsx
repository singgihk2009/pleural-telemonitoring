import { useState } from 'react';
import { View, StyleSheet } from 'react-native';
import { Card, Copy } from './ui';
import { styles, colors } from './theme';

export function VasControl({
  value,
  onChange,
}: {
  value: number | null;
  onChange(value: number): void;
}) {
  const [width, setWidth] = useState(0);
  const choose = (x: number) => {
    if (width > 24)
      onChange(Math.max(0, Math.min(100, Math.round(((x - 12) / (width - 24)) * 100))));
  };
  return (
    <Card>
      <Copy style={styles.sectionTitle}>Seberapa sesak Anda hari ini?</Copy>
      <Copy>Sentuh dan geser garis. 0 = tidak sesak, 100 = sangat sesak.</Copy>
      <View style={local.vasValue}>
        <Copy accessibilityLiveRegion="polite" style={local.vasNumber}>
          {value === null ? '— —' : value} mm
        </Copy>
      </View>
      <View
        testID="vas-touch-area"
        accessible
        accessibilityRole="adjustable"
        accessibilityLabel="Tingkat sesak"
        accessibilityValue={
          value === null
            ? { text: 'Belum dipilih' }
            : { min: 0, max: 100, now: value, text: `${value} milimeter` }
        }
        accessibilityActions={[
          { name: 'increment', label: 'Tambah satu milimeter' },
          { name: 'decrement', label: 'Kurangi satu milimeter' },
        ]}
        onAccessibilityAction={(event) => {
          if (event.nativeEvent.actionName === 'increment')
            onChange(Math.min(100, (value ?? -1) + 1));
          if (event.nativeEvent.actionName === 'decrement') onChange(Math.max(0, (value ?? 1) - 1));
        }}
        style={{ minHeight: 56, justifyContent: 'center' }}
        onLayout={(event) => setWidth(event.nativeEvent.layout.width)}
        onStartShouldSetResponderCapture={() => true}
        onStartShouldSetResponder={() => true}
        onResponderTerminationRequest={() => false}
        onResponderGrant={(event) => choose(event.nativeEvent.locationX)}
        onResponderMove={(event) => choose(event.nativeEvent.locationX)}
      >
        <View
          pointerEvents="none"
          importantForAccessibility="no-hide-descendants"
          style={{
            marginHorizontal: 12,
            height: 4,
            borderRadius: 2,
            backgroundColor: colors.muted,
          }}
        >
          {value !== null && (
            <>
              <View
                style={{
                  position: 'absolute',
                  left: 0,
                  top: 0,
                  bottom: 0,
                  width: `${value}%`,
                  borderRadius: 2,
                  backgroundColor: colors.blue,
                }}
              />
              <View
                style={{
                  position: 'absolute',
                  left: `${value}%`,
                  top: -8,
                  transform: [{ translateX: -10 }],
                  width: 20,
                  height: 20,
                  borderRadius: 10,
                  backgroundColor: colors.blue,
                  borderWidth: 2,
                  borderColor: 'white',
                }}
              />
            </>
          )}
        </View>
      </View>
      <View style={{ flexDirection: 'row', justifyContent: 'space-between' }}>
        <Copy>0</Copy>
        <Copy>50</Copy>
        <Copy>100</Copy>
      </View>
      <Copy style={styles.muted}>Tanpa nilai awal. Pilih nilai sendiri sebelum melanjutkan.</Copy>
    </Card>
  );
}

const local = StyleSheet.create({
  vasValue: { borderRadius: 16, padding: 24, backgroundColor: '#EDF4FC', alignItems: 'center' },
  vasNumber: { fontSize: 36, lineHeight: 48, fontWeight: '700', color: colors.navy },
});
