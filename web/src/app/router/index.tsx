import { lazy } from 'react'
import { createBrowserRouter, Navigate } from 'react-router'
import { AuthGuard } from '@/app/guards/AuthGuard'
import { GuestGuard } from '@/app/guards/GuestGuard'
import { PermissionGuard } from '@/app/guards/PermissionGuard'
import { AppLayout } from '@/app/layouts/AppLayout'
import { AuthLayout } from '@/app/layouts/AuthLayout'
import { Permission } from '@/shared/constants/permissions'
import { NotFoundPage } from './NotFoundPage'
import { WorkspaceLayout } from '@/modules/workspace/layout/WorkspaceLayout'
import { WsGuard } from '@/modules/workspace/layout/WsGuard'

const LoginPage = lazy(() => import('@/modules/auth/pages/LoginPage'))
const PaymentPendingPage = lazy(() => import('@/modules/auth/pages/PaymentPendingPage'))
const DashboardPage = lazy(() => import('@/modules/dashboard/pages/DashboardPage'))
const UsersListPage = lazy(() => import('@/modules/users/pages/UsersListPage'))
const UserCreatePage = lazy(() => import('@/modules/users/pages/UserCreatePage'))
const UserEditPage = lazy(() => import('@/modules/users/pages/UserEditPage'))
const RolesListPage = lazy(() => import('@/modules/roles/pages/RolesListPage'))
const RoleCreatePage = lazy(() => import('@/modules/roles/pages/RoleCreatePage'))
const RoleEditPage = lazy(() => import('@/modules/roles/pages/RoleEditPage'))
const ApiTokensListPage = lazy(() => import('@/modules/api-tokens/pages/ApiTokensListPage'))
const ApiTokenCreatePage = lazy(() => import('@/modules/api-tokens/pages/ApiTokenCreatePage'))
const WebhooksListPage = lazy(() => import('@/modules/webhooks/pages/WebhooksListPage'))
const WebhookCreatePage = lazy(() => import('@/modules/webhooks/pages/WebhookCreatePage'))
const WebhookEditPage = lazy(() => import('@/modules/webhooks/pages/WebhookEditPage'))
const PlansListPage = lazy(() => import('@/modules/billing/pages/PlansListPage'))
const PlanCreatePage = lazy(() => import('@/modules/billing/pages/PlanCreatePage'))
const PlanEditPage = lazy(() => import('@/modules/billing/pages/PlanEditPage'))
const SubscriptionPage = lazy(() => import('@/modules/billing/pages/SubscriptionPage'))
const InvoicesListPage = lazy(() => import('@/modules/billing/pages/InvoicesListPage'))
const AuditPage = lazy(() => import('@/modules/audit/pages/AuditPage'))
const AssistantPage = lazy(() => import('@/modules/assistant/pages/AssistantPage'))
const TenantsListPage = lazy(() => import('@/modules/tenants/pages/TenantsListPage'))
const TenantCreatePage = lazy(() => import('@/modules/tenants/pages/TenantCreatePage'))

const WsDashboardPage = lazy(() => import('@/modules/workspace/pages/DashboardPage'))
const WsProjectsPage = lazy(() => import('@/modules/workspace/pages/ProjectsPage'))
const WsProjectPage = lazy(() => import('@/modules/workspace/pages/ProjectPage'))
const WsProjectOverview = lazy(() => import('@/modules/workspace/pages/ProjectPage').then((m) => ({ default: m.ProjectOverview })))
const WsProjectBoard = lazy(() => import('@/modules/workspace/pages/ProjectPage').then((m) => ({ default: m.ProjectBoard })))
const WsProjectTasks = lazy(() => import('@/modules/workspace/pages/ProjectPage').then((m) => ({ default: m.ProjectTasks })))
const WsProjectActivity = lazy(() => import('@/modules/workspace/pages/ProjectPage').then((m) => ({ default: m.ProjectActivity })))
const WsTasksPage = lazy(() => import('@/modules/workspace/pages/TasksPage'))
const WsTaskPage = lazy(() => import('@/modules/workspace/pages/TaskPage'))
const WsBoardPage = lazy(() => import('@/modules/workspace/pages/BoardPage'))
const WsBoardSettingsPage = lazy(() => import('@/modules/workspace/pages/BoardPage').then((m) => ({ default: m.BoardSettingsPage })))
const WsClientsPage = lazy(() => import('@/modules/workspace/pages/ClientsPage'))
const WsClientPage = lazy(() => import('@/modules/workspace/pages/ClientsPage').then((m) => ({ default: m.ClientPage })))
const WsTeamPage = lazy(() => import('@/modules/workspace/pages/TeamPage'))
const WsNotificationsPage = lazy(() => import('@/modules/workspace/pages/NotificationsPage'))
const WsConnectionsPage = lazy(() => import('@/modules/workspace/pages/ConnectionsPage'))

export const router = createBrowserRouter([
  {
    element: <GuestGuard />,
    children: [
      {
        element: <AuthLayout />,
        children: [
          { path: '/auth/login', element: <LoginPage /> },
        ],
      },
    ],
  },
  {
    element: <AuthGuard />,
    children: [
      {
        path: '/pagamento',
        element: <PaymentPendingPage />,
      },
      {
        path: '/w',
        element: <WorkspaceLayout />,
        children: [
          { index: true, element: <WsDashboardPage /> },
          { path: 'projects', element: <WsGuard permission={Permission.PROJECT_READ}><WsProjectsPage /></WsGuard> },
          {
            path: 'projects/:id',
            element: <WsGuard permission={Permission.PROJECT_READ}><WsProjectPage /></WsGuard>,
            children: [
              { index: true, element: <WsProjectOverview /> },
              { path: 'board', element: <WsProjectBoard /> },
              { path: 'tasks', element: <WsProjectTasks /> },
              { path: 'activity', element: <WsProjectActivity /> },
            ],
          },
          { path: 'tasks', element: <WsGuard permission={Permission.TASK_READ}><WsTasksPage /></WsGuard> },
          { path: 'tasks/:id', element: <WsGuard permission={Permission.TASK_READ}><WsTaskPage /></WsGuard> },
          { path: 'board', element: <WsGuard permission={Permission.BOARD_READ}><WsBoardPage /></WsGuard> },
          { path: 'board/settings', element: <WsGuard permission={Permission.BOARD_MANAGE} professional><WsBoardSettingsPage /></WsGuard> },
          { path: 'boards/*', element: <Navigate to="/w/board" replace /> },
          { path: 'clients', element: <WsGuard permission={Permission.CLIENT_READ} professional><WsClientsPage /></WsGuard> },
          { path: 'clients/:id', element: <WsGuard permission={Permission.CLIENT_READ} professional><WsClientPage /></WsGuard> },
          { path: 'team', element: <WsGuard permission={Permission.TASK_ASSIGN} professional><WsTeamPage /></WsGuard> },
          { path: 'notifications', element: <WsNotificationsPage /> },
          { path: 'connections', element: <WsGuard permission={Permission.MCP_USE}><WsConnectionsPage /></WsGuard> },
        ],
      },
      {
        element: <AppLayout />,
        children: [
          { path: '/', element: <Navigate to="/w" replace /> },
          { path: '/dashboard', element: <DashboardPage /> },
          {
            path: '/users',
            element: (
              <PermissionGuard permission={Permission.USER_READ}>
                <UsersListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/users/create',
            element: (
              <PermissionGuard permission={Permission.USER_CREATE}>
                <UserCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/users/:id/edit',
            element: (
              <PermissionGuard permission={Permission.USER_UPDATE}>
                <UserEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/roles',
            element: (
              <PermissionGuard permission={Permission.ROLE_READ}>
                <RolesListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/roles/create',
            element: (
              <PermissionGuard permission={Permission.ROLE_CREATE}>
                <RoleCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/roles/:id/edit',
            element: (
              <PermissionGuard permission={Permission.ROLE_UPDATE}>
                <RoleEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/api-tokens',
            element: (
              <PermissionGuard permission={Permission.API_TOKEN_READ}>
                <ApiTokensListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/api-tokens/create',
            element: (
              <PermissionGuard permission={Permission.API_TOKEN_CREATE}>
                <ApiTokenCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/webhooks',
            element: (
              <PermissionGuard permission={Permission.WEBHOOK_READ}>
                <WebhooksListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/webhooks/create',
            element: (
              <PermissionGuard permission={Permission.WEBHOOK_CREATE}>
                <WebhookCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/webhooks/:id/edit',
            element: (
              <PermissionGuard permission={Permission.WEBHOOK_UPDATE}>
                <WebhookEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/billing/plans',
            element: (
              <PermissionGuard permission={Permission.PLAN_READ} requiresUmbrella>
                <PlansListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/billing/plans/create',
            element: (
              <PermissionGuard permission={Permission.PLAN_CREATE} requiresUmbrella>
                <PlanCreatePage />
              </PermissionGuard>
            ),
          },
          {
            path: '/billing/plans/:id/edit',
            element: (
              <PermissionGuard permission={Permission.PLAN_UPDATE} requiresUmbrella>
                <PlanEditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/billing/subscription',
            element: (
              <PermissionGuard permission={Permission.SUBSCRIPTION_READ} requiresChildTenant>
                <SubscriptionPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/billing/invoices',
            element: (
              <PermissionGuard permission={Permission.INVOICE_READ} requiresChildTenant>
                <InvoicesListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/audit',
            element: (
              <PermissionGuard permission={Permission.AUDIT_VIEW}>
                <AuditPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/tenants',
            element: (
              <PermissionGuard permission={Permission.TENANT_READ} requiresUmbrella>
                <TenantsListPage />
              </PermissionGuard>
            ),
          },
          {
            path: '/tenants/create',
            element: (
              <PermissionGuard permission={Permission.TENANT_CREATE} requiresUmbrella>
                <TenantCreatePage />
              </PermissionGuard>
            ),
          },
        ],
      },
      {
        path: '/assistant/:id?',
        element: (
          <PermissionGuard permission={Permission.ASSISTANT_VIEW}>
            <AssistantPage />
          </PermissionGuard>
        ),
      },
    ],
  },
  { path: '*', element: <NotFoundPage /> },
])
