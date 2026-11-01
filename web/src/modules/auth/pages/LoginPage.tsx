import { useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { AlertCircle, ArrowRight, Eye, EyeOff } from 'lucide-react'
import { isApiError } from '@/shared/api/errors'
import { applyApiErrorsToForm } from '@/shared/utils/forms'
import { Button, Field, TextInput } from '@/modules/workspace/ui'
import { loginSchema, type LoginFormValues } from '../schemas/auth.schema'
import { useLogin } from '../hooks/useAuth'

export default function LoginPage() {
  const login = useLogin()
  const [showPassword, setShowPassword] = useState(false)

  const form = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  })
  const { errors } = form.formState

  const onSubmit = async (values: LoginFormValues) => {
    try {
      await login.mutateAsync(values)
    } catch (error) {
      if (isApiError(error) && error.status === 422) {
        applyApiErrorsToForm(form, error)
      }
    }
  }

  const generalError = login.isError && isApiError(login.error) && login.error.status !== 422 ? login.error.message : null

  return (
    <div className="ws-rise w-full max-w-[360px]">
      <div className="mb-8">
        <p className="ws-eyebrow">Acesso</p>
        <h1 className="mt-2 text-[22px] font-semibold tracking-tight text-ws-ink">Entre na sua conta</h1>
        <p className="mt-1.5 text-[13.5px] text-ws-ink-2">Use o e-mail e a senha cadastrados pela sua equipe.</p>
      </div>

      {generalError && (
        <div role="alert" className="mb-5 flex items-start gap-2 rounded-ws bg-ws-danger-tint px-3 py-2.5 text-[13px] text-ws-danger">
          <AlertCircle className="mt-px size-4 shrink-0" aria-hidden="true" />
          <span>{generalError}</span>
        </div>
      )}

      <form noValidate onSubmit={form.handleSubmit(onSubmit)} className="flex flex-col gap-4">
        <Field label="E-mail" htmlFor="login-email" error={errors.email?.message}>
          <TextInput
            id="login-email"
            type="email"
            placeholder="voce@empresa.com"
            autoComplete="email"
            autoFocus
            className="h-10"
            aria-invalid={!!errors.email}
            {...form.register('email')}
          />
        </Field>

        <Field label="Senha" htmlFor="login-password" error={errors.password?.message}>
          <div className="relative">
            <TextInput
              id="login-password"
              type={showPassword ? 'text' : 'password'}
              placeholder="••••••••"
              autoComplete="current-password"
              className="h-10 pr-10"
              aria-invalid={!!errors.password}
              {...form.register('password')}
            />
            <button
              type="button"
              onClick={() => setShowPassword((value) => !value)}
              aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
              className="absolute inset-y-0 right-0 flex w-10 items-center justify-center text-ws-ink-3 hover:text-ws-ink"
            >
              {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </button>
          </div>
        </Field>

        <Button type="submit" variant="primary" className="mt-2 h-10 w-full" loading={login.isPending}>
          Entrar
          {!login.isPending && <ArrowRight className="size-4" aria-hidden="true" />}
        </Button>
      </form>

      <p className="mt-8 text-[12.5px] text-ws-ink-3">
        Não tem acesso? Peça um convite ao administrador da sua organização.
      </p>
    </div>
  )
}
