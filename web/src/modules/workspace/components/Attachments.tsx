import { useRef } from 'react'
import { Download, FileText, Lock, Paperclip, X } from 'lucide-react'
import { cn } from '@/shared/utils/cn'
import { Button, IconButton } from '../ui'
import { acceptFiles, ATTACHMENT_ACCEPT } from '../lib/attachments'
import { fileSize } from '../lib/format'
import type { Attachment } from '../lib/types'
import { wsApi } from '../services/workspace.api'

/** Botão "Anexar" que abre o seletor de arquivos (múltiplos). */
export function AttachButton({
  onFiles,
  label = 'Anexar',
  loading,
  disabled,
}: {
  onFiles: (files: File[]) => void
  label?: string
  loading?: boolean
  disabled?: boolean
}) {
  const input = useRef<HTMLInputElement>(null)

  return (
    <>
      <input
        ref={input}
        type="file"
        multiple
        accept={ATTACHMENT_ACCEPT}
        className="hidden"
        onChange={(e) => {
          const files = acceptFiles(e.target.files ?? [])
          if (files.length) onFiles(files)
          e.target.value = ''
        }}
      />
      <Button
        type="button"
        size="sm"
        variant="ghost"
        loading={loading}
        disabled={disabled}
        icon={<Paperclip className="size-3.5" />}
        onClick={() => input.current?.click()}
      >
        {label}
      </Button>
    </>
  )
}

/** Arquivos selecionados localmente, ainda não enviados. */
export function PendingFiles({ files, onRemove }: { files: File[]; onRemove: (index: number) => void }) {
  if (!files.length) return null

  return (
    <ul className="flex flex-wrap gap-2">
      {files.map((file, index) => (
        <li key={`${file.name}-${index}`} className="flex max-w-full items-center gap-1.5 rounded-ws-sm border border-ws-line bg-ws-panel py-1 pr-1 pl-2 text-[12px]">
          <Paperclip className="size-3 shrink-0 text-ws-ink-3" aria-hidden="true" />
          <span className="min-w-0 truncate text-ws-ink">{file.name}</span>
          <span className="shrink-0 tabular-nums text-ws-ink-3">{fileSize(file.size)}</span>
          <IconButton label={`Remover ${file.name}`} className="size-5" onClick={() => onRemove(index)}>
            <X className="size-3" />
          </IconButton>
        </li>
      ))}
    </ul>
  )
}

/**
 * Lista de anexos já enviados: imagens aparecem como miniaturas (abrem em
 * nova aba), demais arquivos como linhas com download.
 */
export function AttachmentList({
  attachments,
  showVisibility,
  canRemove,
  onRemove,
  compact,
}: {
  attachments: Attachment[]
  showVisibility?: boolean
  canRemove?: (attachment: Attachment) => boolean
  onRemove?: (attachment: Attachment) => void
  compact?: boolean
}) {
  if (!attachments.length) return null

  const images = attachments.filter((a) => a.is_image)
  const files = attachments.filter((a) => !a.is_image)
  const removable = (a: Attachment) => Boolean(onRemove && canRemove?.(a))

  return (
    <div className="flex flex-col gap-2">
      {images.length > 0 && (
        <ul className="flex flex-wrap gap-2">
          {images.map((image) => (
            <li key={image.id} className="group relative">
              <a href={wsApi.tasks.inlineUrl(image.id)} target="_blank" rel="noreferrer" title={image.name} className="block">
                <img
                  src={wsApi.tasks.inlineUrl(image.id)}
                  alt={image.name}
                  loading="lazy"
                  className={cn('rounded-ws-sm border border-ws-line bg-ws-sunken object-cover', compact ? 'size-16' : 'size-24')}
                />
              </a>
              {showVisibility && image.visibility === 'internal' && (
                <span className="absolute bottom-1 left-1 rounded-full bg-ws-panel/90 p-0.5" title="Interno">
                  <Lock className="size-3 text-ws-ink-3" aria-label="Interno" />
                </span>
              )}
              {removable(image) && (
                <IconButton
                  label={`Remover ${image.name}`}
                  className="absolute top-1 right-1 size-5 bg-ws-panel/90 opacity-0 group-hover:opacity-100 focus:opacity-100"
                  onClick={() => onRemove?.(image)}
                >
                  <X className="size-3" />
                </IconButton>
              )}
            </li>
          ))}
        </ul>
      )}
      {files.length > 0 && (
        <ul className="flex flex-col">
          {files.map((file) => (
            <li key={file.id} className={cn('flex items-center gap-2 text-[13px]', compact ? 'py-1' : 'py-2')}>
              <FileText className="size-3.5 shrink-0 text-ws-ink-3" aria-hidden="true" />
              <a href={wsApi.tasks.downloadUrl(file.id)} className="min-w-0 flex-1 truncate text-ws-ink hover:underline">
                {file.name}
              </a>
              {showVisibility && file.visibility === 'internal' && <Lock className="size-3 text-ws-ink-3" aria-label="Interno" />}
              <span className="tabular-nums text-[11px] text-ws-ink-3">{fileSize(file.size)}</span>
              <a href={wsApi.tasks.downloadUrl(file.id)} aria-label={`Baixar ${file.name}`} className="text-ws-ink-3 hover:text-ws-ink">
                <Download className="size-3.5" />
              </a>
              {removable(file) && (
                <IconButton label={`Remover ${file.name}`} className="size-6" onClick={() => onRemove?.(file)}>
                  <X className="size-3.5" />
                </IconButton>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
