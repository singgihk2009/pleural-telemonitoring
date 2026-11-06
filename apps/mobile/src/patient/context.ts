import { createContext, useContext } from 'react';
import type { Profile, Session } from './api';
export type State = {
  session: Session | null;
  profile: Profile | null;
  ready: boolean;
  busy: boolean;
  offline: boolean;
  message: string;
  acknowledged: boolean;
};
export type Context = State & {
  login(code: string, pin: string): Promise<void>;
  logout(): Promise<void>;
  retry(): Promise<void>;
  acknowledge(): void;
  api<T>(path: string): Promise<T>;
};
export const PatientContext = createContext<Context | null>(null);
export function usePatient() {
  const context = useContext(PatientContext);
  if (!context) throw new Error('PatientProvider required');
  return context;
}
