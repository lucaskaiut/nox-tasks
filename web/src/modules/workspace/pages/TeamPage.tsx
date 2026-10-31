import { Link } from 'react-router'
import { useQuery } from '@tanstack/react-query'
import { Users } from 'lucide-react'
import { Permission } from '@/shared/constants/permissions'
import { ButtonLink, Empty, Initials, LoadingBlock, PageHeader } from '../ui'
import { useMembers, useProfile } from '../hooks/useWorkspace'
import { wsApi } from '../services/workspace.api'

/** Profissionais e sua carga atual. Cadastro e papéis ficam no ACL da plataforma. */
export default function TeamPage() {
  const { can } = useProfile()
  const members = useMembers()
  const load = useQuery({
    queryKey: ['ws', 'team-load'],
    queryFn: () => wsApi.tasks.list({ state: 'open', per_page: 100 }),
  })

  const counts = new Map<string, { open: number; overdue: number }>()
  load.data?.data.forEach((task) => {
    if (!task.assignee) return
    const entry = counts.get(task.assignee.id) ?? { open: 0, overdue: 0 }
    entry.open += 1
    if (task.is_overdue) entry.overdue += 1
    counts.set(task.assignee.id, entry)
  })

  return (
    <div className="ws-rise">
      <PageHeader
        eyebrow="Pessoas"
        title="Equipe"
        description="Profissionais que podem ser responsáveis por projetos e tarefas."
        actions={can(Permission.USER_CREATE) && <ButtonLink to="/users/create">Convidar profissional</ButtonLink>}
      />
      {members.isLoading ? (
        <LoadingBlock />
      ) : members.data?.data.length ? (
        <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
          {members.data.data.map((member) => {
            const stats = counts.get(member.id)

            return (
              <li key={member.id} className="flex items-center gap-3 rounded-ws bg-ws-panel shadow-ws p-3">
                <Initials name={member.name} size="md" />
                <div className="min-w-0 flex-1">
                  <p className="truncate text-[13.5px] font-medium text-ws-ink">{member.name}</p>
                  <p className="truncate text-[12px] text-ws-ink-3">{member.email}</p>
                </div>
                <Link to={`/w/tasks?assignee=${member.id}`} className="text-right" title="Ver tarefas abertas">
                  <span className="block tabular-nums text-[18px] leading-none text-ws-ink">{stats?.open ?? 0}</span>
                  <span className="text-[11px] text-ws-ink-3">{stats?.overdue ? <span className="text-ws-danger">{stats.overdue} atrasada(s)</span> : 'abertas'}</span>
                </Link>
              </li>
            )
          })}
        </ul>
      ) : (
        <Empty icon={Users} title="Nenhum profissional" />
      )}
      {can(Permission.ROLE_READ) && (
        <p className="mt-6 text-[12.5px] text-ws-ink-3">
          Permissões são definidas pelos perfis de acesso da plataforma (Profissional, Cliente e outros) —{' '}
          <Link to="/roles" className="underline underline-offset-2 hover:text-ws-ink">
            gerenciar perfis
          </Link>
          .
        </p>
      )}
    </div>
  )
}
