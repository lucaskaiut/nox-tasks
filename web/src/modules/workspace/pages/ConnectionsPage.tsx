import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Bot, Copy, KeyRound, Plus, ShieldCheck } from 'lucide-react'
import { toast } from '@/shared/stores/toast.store'
import { Button, Confirm, Empty, Field, LoadingBlock, PageHeader, Panel, SelectInput, Sheet, TextInput } from '../ui'
import { wsKeys } from '../hooks/keys'
import { useProfile, useWsMutation } from '../hooks/useWorkspace'
import { dateTime, relative } from '../lib/format'
import type { McpToken } from '../lib/types'
import { wsApi } from '../services/workspace.api'

function copy(text: string) {
  void navigator.clipboard?.writeText(text)
  toast.success('Copiado')
}

/** Conexões de agentes de IA via MCP — tokens pessoais do próprio usuário. */
export default function ConnectionsPage() {
  const { isClient } = useProfile()
  const tokens = useQuery({ queryKey: wsKeys.mcp, queryFn: wsApi.mcp.list })
  const [creating, setCreating] = useState(false)
  const [name, setName] = useState('')
  const [expires, setExpires] = useState('90')
  const [issued, setIssued] = useState<{ token: string; server_url: string } | null>(null)
  const [revoking, setRevoking] = useState<McpToken | null>(null)

  const create = useWsMutation((body: { name: string; expires_in_days: number | null }) => wsApi.mcp.create(body))
  const revoke = useWsMutation((id: number) => wsApi.mcp.revoke(id), 'Conexão revogada')

  const serverUrl = tokens.data?.server_url ?? ''
  const snippet = issued
    ? `claude mcp add --transport http nox-tasks ${issued.server_url} \\\n  --header "Authorization: Bearer ${issued.token}"`
    : ''

  return (
    <div className="ws-rise mx-auto max-w-4xl">
      <PageHeader
        eyebrow="Integrações"
        title="Conexões de IA"
        description="Conecte um agente (Claude, Cursor e outros clientes MCP) para que ele consulte e organize projetos e tarefas em seu nome."
        actions={
          <Button variant="primary" icon={<Plus className="size-3.5" />} onClick={() => setCreating(true)}>
            Nova conexão
          </Button>
        }
      />

      <div className="mb-6 grid gap-4 md:grid-cols-3">
        <div className="flex gap-3 rounded-ws bg-ws-panel shadow-ws p-4 md:col-span-2">
          <ShieldCheck className="size-5 shrink-0 text-ws-accent" aria-hidden="true" />
          <div className="text-[13px] text-ws-ink-2">
            <p className="font-medium text-ws-ink">O agente age como você — e só como você.</p>
            <p className="mt-1">
              Ele usa as mesmas permissões da sua conta{isClient ? ' e enxerga apenas os projetos e tarefas compartilhados com sua empresa' : ''}. Tudo o que fizer
              fica registrado no histórico com a marca “agente IA”. Revogue a qualquer momento.
            </p>
          </div>
        </div>
        <div className="rounded-ws bg-ws-panel shadow-ws p-4">
          <p className="ws-eyebrow">Endereço do servidor</p>
          <button type="button" onClick={() => copy(serverUrl)} className="mt-1.5 flex w-full items-center gap-2 text-left tabular-nums text-[12px] break-all text-ws-ink hover:text-ws-accent">
            {serverUrl}
            <Copy className="size-3.5 shrink-0" />
          </button>
        </div>
      </div>

      <Panel eyebrow="Tokens ativos" title="Suas conexões" bodyClassName="p-1.5 pt-0">
        {tokens.isLoading ? (
          <div className="p-4">
            <LoadingBlock rows={2} />
          </div>
        ) : tokens.data?.tokens.length ? (
          <ul>
            {tokens.data.tokens.map((token) => (
              <li key={token.id} className="flex items-center gap-3 px-4 py-3">
                <Bot className="size-4 text-ws-ink-3" aria-hidden="true" />
                <div className="min-w-0 flex-1">
                  <p className="truncate text-[13.5px] text-ws-ink">{token.name}</p>
                  <p className="tabular-nums text-[11px] text-ws-ink-3">
                    criado {relative(token.created_at)} · {token.last_used_at ? `usado ${relative(token.last_used_at)}` : 'nunca usado'} ·{' '}
                    {token.expires_at ? `expira ${dateTime(token.expires_at)}` : 'sem expiração'}
                  </p>
                </div>
                <Button size="sm" variant="danger" onClick={() => setRevoking(token)}>
                  Revogar
                </Button>
              </li>
            ))}
          </ul>
        ) : (
          <div className="p-4">
            <Empty icon={KeyRound} title="Nenhuma conexão" description="Crie um token e configure-o no seu cliente MCP." />
          </div>
        )}
      </Panel>

      <Sheet
        open={creating}
        onClose={() => {
          setCreating(false)
          setIssued(null)
          setName('')
        }}
        eyebrow="Nova conexão"
        title={issued ? 'Token criado' : 'Conectar um agente'}
        footer={
          issued ? (
            <Button
              variant="primary"
              onClick={() => {
                setCreating(false)
                setIssued(null)
                setName('')
              }}
            >
              Concluir
            </Button>
          ) : (
            <Button
              variant="primary"
              loading={create.isPending}
              disabled={!name.trim()}
              onClick={async () => {
                const result = await create.mutateAsync({ name: name.trim(), expires_in_days: expires ? Number(expires) : null })
                setIssued({ token: result.token, server_url: result.server_url })
              }}
            >
              Gerar token
            </Button>
          )
        }
      >
        {issued ? (
          <div className="flex flex-col gap-4">
            <p className="text-[13px] text-ws-warn">Copie o token agora — ele não será exibido novamente.</p>
            <Field label="Token">
              <button type="button" onClick={() => copy(issued.token)} className="flex items-center gap-2 rounded-ws-sm bg-ws-sunken px-2.5 py-2 text-left tabular-nums text-[12px] break-all text-ws-ink">
                {issued.token}
                <Copy className="size-3.5 shrink-0" />
              </button>
            </Field>
            <Field label="Claude Code">
              <pre className="overflow-x-auto rounded-ws bg-ws-ink p-3 tabular-nums text-[11.5px] text-ws-canvas">{snippet}</pre>
            </Field>
            <Button size="sm" icon={<Copy className="size-3.5" />} onClick={() => copy(snippet)}>
              Copiar comando
            </Button>
          </div>
        ) : (
          <div className="flex flex-col gap-4">
            <Field label="Nome da conexão" htmlFor="m-name" hint="Ex.: Claude Desktop — notebook">
              <TextInput id="m-name" autoFocus value={name} onChange={(e) => setName(e.target.value)} maxLength={60} />
            </Field>
            <Field label="Validade" htmlFor="m-exp">
              <SelectInput id="m-exp" value={expires} onChange={(e) => setExpires(e.target.value)}>
                <option value="7">7 dias</option>
                <option value="30">30 dias</option>
                <option value="90">90 dias</option>
                <option value="365">1 ano</option>
                <option value="">Sem expiração</option>
              </SelectInput>
            </Field>
          </div>
        )}
      </Sheet>

      <Confirm
        open={Boolean(revoking)}
        destructive
        title={`Revogar "${revoking?.name}"?`}
        description="O agente que usa este token perde o acesso imediatamente."
        confirmLabel="Revogar"
        loading={revoke.isPending}
        onClose={() => setRevoking(null)}
        onConfirm={async () => {
          await revoke.mutateAsync(revoking!.id)
          setRevoking(null)
        }}
      />
    </div>
  )
}
