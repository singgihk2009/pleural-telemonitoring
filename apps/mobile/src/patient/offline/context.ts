import { createContext, useContext } from 'react';
import type { AssessmentStatus } from '../assessment';
import type { Record, DraftBody } from './core';
export type Context = {
  available: boolean; enabled: boolean; loading: boolean; scope: string; record: Record | null;
  lockedPredecessors: number; error: string; syncing: boolean; writing: boolean;
  initialize(status: AssessmentStatus): Promise<Record | undefined>;
  update(body: Pick<DraftBody,'vas_mm' | 'filled_by' | 'answers'>): Promise<Record | undefined>;
  confirm(): Promise<Record | undefined>; discard(confirmed: boolean): Promise<void>;
  synchronize(manual?: boolean): Promise<void>;
};
export const OfflineContext = createContext<Context | null>(null);
export const useOfflineQueue = () => useContext(OfflineContext);
