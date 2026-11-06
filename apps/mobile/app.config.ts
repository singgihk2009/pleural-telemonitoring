import type { ConfigContext, ExpoConfig } from 'expo/config';

export default ({ config }: ConfigContext): ExpoConfig => {
  const variant = process.env.APP_VARIANT;
  if (variant && variant !== 'uat') throw new Error('Unsupported APP_VARIANT');
  if (!variant) return config as ExpoConfig;

  // EAS evaluates this before bundling. Fail closed if UAT could target another API.
  if (process.env.EXPO_PUBLIC_API_BASE_URL !== 'https://telenapas.sintaraniapp.net/api/v1') {
    throw new Error('UAT requires the approved development HTTPS API');
  }
  return {
    ...config,
    name: 'TeleNapas UAT',
    slug: config.slug ?? 'pleural-telemonitoring',
    scheme: 'telenapas-uat',
    android: {
      ...config.android,
      package: 'id.telenapas.patient.uat',
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
