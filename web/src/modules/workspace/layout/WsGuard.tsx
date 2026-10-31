import type { ReactNode } from 'react'
import { ShieldAlert } from 'lucide-react'
import type { Permission } from '@/shared/constants/permissions'
import { ButtonLink, Empty } from '../ui'
import { useProfile } from '../hooks/useWorkspace'

/**
 * Guarda de rota do Workspace. Apenas evita telas inúteis — a autorização
 * real é sempre feita pela API.
 */
export function WsGuard({ permission, professional, children }: { permission: Permission; professional?: boolean; children: ReactNode }) {
  const { can, canManage } = useProfile()
  const allowed = professional ? canManage(permission) : can(permission)

  if (!allowed) {
    return (
      <Empty
        icon={ShieldAlert}
        title="Acesso restrito"
        description="Seu perfil não tem acesso a esta área."
        action={<ButtonLink to="/w">Voltar ao início</ButtonLink>}
      />
    )
  }

  return children
}
