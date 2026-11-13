import { toast } from '@/shared/stores/toast.store'

/** Mesmas extensões e limite aceitos pela API (WorkspaceRules::file). */
export const ATTACHMENT_ACCEPT = '.pdf,.png,.jpg,.jpeg,.gif,.webp,.txt,.csv,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.md,.json'
export const ATTACHMENT_MAX_BYTES = 20 * 1024 * 1024
const EXTENSIONS = ATTACHMENT_ACCEPT.split(',')

/** Filtra arquivos inválidos (tipo/tamanho), avisando o usuário. */
export function acceptFiles(files: Iterable<File>): File[] {
  const accepted: File[] = []
  for (const file of files) {
    const name = file.name.toLowerCase()
    if (!EXTENSIONS.some((ext) => name.endsWith(ext))) {
      toast.error('Tipo de arquivo não permitido', file.name)
    } else if (file.size > ATTACHMENT_MAX_BYTES) {
      toast.error('Arquivo maior que 20 MB', file.name)
    } else {
      accepted.push(file)
    }
  }
  return accepted
}
