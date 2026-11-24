import type { ConfigContext, ExpoConfig } from 'expo/config';

export default ({ config }: ConfigContext): ExpoConfig => {
  const variant = process.env.APP_VARIANT;
  if (variant && variant !== 'uat' && variant !== 'synthetic-uat') throw new Error('Unsupported APP_VARIANT');
  if (!variant) return config as ExpoConfig;

  const synthetic = variant === 'synthetic-uat';
  const api = synthetic ? 'https://uat-telenapas.sintaraniapp.net/api/v1' : 'https://telenapas.sintaraniapp.net/api/v1';

  // EAS evaluates this before bundling. Fail closed if UAT could target another API.
  if (process.env.EXPO_PUBLIC_API_BASE_URL !== api) {
    throw new Error('UAT requires the approved development HTTPS API');
  }
  return {
    ...config,
    name: synthetic ? 'TeleNapas Synthetic UAT' : 'TeleNapas UAT',
    slug: config.slug ?? 'pleural-telemonitoring',
    scheme: synthetic ? 'telenapas-synthetic-uat' : 'telenapas-uat',
    android: {
      ...config.android,
      package: synthetic ? 'id.telenapas.patient.syntheticuat' : 'id.telenapas.patient.uat',
      // Release UAT has no overlay, shared-media or vibration feature.
      blockedPermissions: [
        ...(config.android?.blockedPermissions ?? []),
        'android.permission.SYSTEM_ALERT_WINDOW',
        'android.permission.READ_EXTERNAL_STORAGE',
        'android.permission.WRITE_EXTERNAL_STORAGE',
        'android.permission.VIBRATE',
      ],
    },
  };
};
