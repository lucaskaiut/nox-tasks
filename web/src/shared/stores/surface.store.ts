import { create } from 'zustand'

/**
 * Superfície visual ativa: painel administrativo ou Workspace (identidade
 * própria). Permite que elementos globais (ex.: toasts) usem o visual certo.
 */
type Surface = 'admin' | 'workspace'

export const useSurfaceStore = create<{ surface: Surface; setSurface: (surface: Surface) => void }>()((set) => ({
  surface: 'admin',
  setSurface: (surface) => set({ surface }),
}))
