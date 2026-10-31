export const Permission = {
  USER_CREATE: 'user.create',
  USER_READ: 'user.read',
  USER_UPDATE: 'user.update',
  USER_DELETE: 'user.delete',

  TENANT_READ: 'tenant.read',
  TENANT_UPDATE: 'tenant.update',
  TENANT_CREATE: 'tenant.create',

  ROLE_CREATE: 'role.create',
  ROLE_READ: 'role.read',
  ROLE_UPDATE: 'role.update',
  ROLE_DELETE: 'role.delete',

  API_TOKEN_CREATE: 'api-token.create',
  API_TOKEN_READ: 'api-token.read',
  API_TOKEN_UPDATE: 'api-token.update',
  API_TOKEN_DELETE: 'api-token.delete',

  WEBHOOK_CREATE: 'webhook.create',
  WEBHOOK_READ: 'webhook.read',
  WEBHOOK_UPDATE: 'webhook.update',
  WEBHOOK_DELETE: 'webhook.delete',

  PLAN_CREATE: 'plan.create',
  PLAN_READ: 'plan.read',
  PLAN_UPDATE: 'plan.update',
  PLAN_DELETE: 'plan.delete',

  SUBSCRIPTION_READ: 'subscription.read',
  SUBSCRIPTION_UPDATE: 'subscription.update',

  INVOICE_READ: 'invoice.read',

  AUDIT_VIEW: 'audit.view',

  ASSISTANT_VIEW: 'assistant.view',

  CLIENT_READ: 'client.read',
  CLIENT_CREATE: 'client.create',
  CLIENT_UPDATE: 'client.update',
  CLIENT_DELETE: 'client.delete',
  CLIENT_MANAGE_USERS: 'client.manage-users',
  PROJECT_READ: 'project.read',
  PROJECT_CREATE: 'project.create',
  PROJECT_UPDATE: 'project.update',
  PROJECT_ARCHIVE: 'project.archive',
  TASK_READ: 'task.read',
  TASK_CREATE: 'task.create',
  TASK_UPDATE: 'task.update',
  TASK_DELETE: 'task.delete',
  TASK_ASSIGN: 'task.assign',
  TASK_MOVE: 'task.move',
  BOARD_READ: 'board.read',
  BOARD_MANAGE: 'board.manage',
  COMMENT_CREATE: 'comment.create',
  MCP_USE: 'mcp.use',
} as const

export type Permission = (typeof Permission)[keyof typeof Permission]

export interface PermissionGroup {
  label: string
  permissions: Array<{ value: Permission; label: string }>
}

export const PERMISSION_GROUPS: PermissionGroup[] = [
  {
    label: 'Usuários',
    permissions: [
      { value: Permission.USER_READ, label: 'Visualizar usuários' },
      { value: Permission.USER_CREATE, label: 'Criar usuários' },
      { value: Permission.USER_UPDATE, label: 'Editar usuários' },
      { value: Permission.USER_DELETE, label: 'Remover usuários' },
    ],
  },
  {
    label: 'Organização',
    permissions: [
      { value: Permission.TENANT_READ, label: 'Visualizar dados da organização' },
      { value: Permission.TENANT_UPDATE, label: 'Editar dados da organização' },
      { value: Permission.TENANT_CREATE, label: 'Criar empresas' },
    ],
  },
  {
    label: 'Perfis de acesso',
    permissions: [
      { value: Permission.ROLE_READ, label: 'Visualizar perfis' },
      { value: Permission.ROLE_CREATE, label: 'Criar perfis' },
      { value: Permission.ROLE_UPDATE, label: 'Editar perfis' },
      { value: Permission.ROLE_DELETE, label: 'Remover perfis' },
    ],
  },
  {
    label: 'Tokens de API',
    permissions: [
      { value: Permission.API_TOKEN_READ, label: 'Visualizar tokens' },
      { value: Permission.API_TOKEN_CREATE, label: 'Criar tokens' },
      { value: Permission.API_TOKEN_UPDATE, label: 'Editar escopos de tokens' },
      { value: Permission.API_TOKEN_DELETE, label: 'Revogar tokens' },
    ],
  },
  {
    label: 'Webhooks',
    permissions: [
      { value: Permission.WEBHOOK_READ, label: 'Visualizar webhooks' },
      { value: Permission.WEBHOOK_CREATE, label: 'Criar webhooks' },
      { value: Permission.WEBHOOK_UPDATE, label: 'Editar webhooks' },
      { value: Permission.WEBHOOK_DELETE, label: 'Remover webhooks' },
    ],
  },
  {
    label: 'Auditoria',
    permissions: [{ value: Permission.AUDIT_VIEW, label: 'Visualizar auditoria' }],
  },
  {
    label: 'Assistente de IA',
    permissions: [{ value: Permission.ASSISTANT_VIEW, label: 'Usar o assistente de IA' }],
  },
  {
    label: 'Workspace — clientes',
    permissions: [
      { value: Permission.CLIENT_READ, label: 'Visualizar clientes' },
      { value: Permission.CLIENT_CREATE, label: 'Cadastrar clientes' },
      { value: Permission.CLIENT_UPDATE, label: 'Editar clientes' },
      { value: Permission.CLIENT_DELETE, label: 'Remover clientes' },
      { value: Permission.CLIENT_MANAGE_USERS, label: 'Gerenciar acessos de clientes' },
    ],
  },
  {
    label: 'Workspace — projetos e tarefas',
    permissions: [
      { value: Permission.PROJECT_READ, label: 'Visualizar projetos' },
      { value: Permission.PROJECT_CREATE, label: 'Criar projetos' },
      { value: Permission.PROJECT_UPDATE, label: 'Editar projetos' },
      { value: Permission.PROJECT_ARCHIVE, label: 'Arquivar projetos' },
      { value: Permission.TASK_READ, label: 'Visualizar tarefas' },
      { value: Permission.TASK_CREATE, label: 'Criar tarefas' },
      { value: Permission.TASK_UPDATE, label: 'Editar tarefas e subtarefas' },
      { value: Permission.TASK_DELETE, label: 'Excluir tarefas' },
      { value: Permission.TASK_ASSIGN, label: 'Atribuir responsáveis' },
      { value: Permission.TASK_MOVE, label: 'Mover tarefas no Kanban' },
      { value: Permission.COMMENT_CREATE, label: 'Comentar em tarefas' },
    ],
  },
  {
    label: 'Workspace — quadros e IA',
    permissions: [
      { value: Permission.BOARD_READ, label: 'Visualizar quadros' },
      { value: Permission.BOARD_MANAGE, label: 'Configurar quadros e colunas' },
      { value: Permission.MCP_USE, label: 'Conectar agentes de IA (MCP)' },
    ],
  },
  {
    label: 'Assinaturas',
    permissions: [
      { value: Permission.PLAN_READ, label: 'Visualizar planos' },
      { value: Permission.PLAN_CREATE, label: 'Criar planos' },
      { value: Permission.PLAN_UPDATE, label: 'Editar planos' },
      { value: Permission.PLAN_DELETE, label: 'Inativar planos' },
      { value: Permission.SUBSCRIPTION_READ, label: 'Visualizar assinatura' },
      { value: Permission.SUBSCRIPTION_UPDATE, label: 'Gerenciar assinatura' },
      { value: Permission.INVOICE_READ, label: 'Visualizar cobranças' },
    ],
  },
]

/** Permissões de cadastro de planos — só fazem sentido em tenants umbrella. */
export const PLAN_PERMISSIONS: Permission[] = [
  Permission.PLAN_CREATE,
  Permission.PLAN_READ,
  Permission.PLAN_UPDATE,
  Permission.PLAN_DELETE,
]

export function isPlanPermission(permission: Permission | string): boolean {
  return String(permission).startsWith('plan.')
}

export function getPermissionGroups(options?: { includePlanPermissions?: boolean }): PermissionGroup[] {
  const includePlanPermissions = options?.includePlanPermissions ?? true

  if (includePlanPermissions) {
    return PERMISSION_GROUPS
  }

  return PERMISSION_GROUPS.map((group) => ({
    ...group,
    permissions: group.permissions.filter((item) => !isPlanPermission(item.value)),
  })).filter((group) => group.permissions.length > 0)
}
