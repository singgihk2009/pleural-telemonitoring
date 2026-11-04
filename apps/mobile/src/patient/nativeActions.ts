import { Alert, Linking } from 'react-native';
export async function emergency() {
  Alert.alert('Darurat 119', 'Buka telepon untuk menghubungi 119?', [
    { text: 'Batal', style: 'cancel' },
    {
      text: 'Buka telepon',
      onPress: () => {
        void Linking.openURL('tel:119').catch(() =>
          Alert.alert('Telepon tidak tersedia', 'Gunakan telepon lain untuk menghubungi 119.'),
        );
      },
    },
  ]);
}
