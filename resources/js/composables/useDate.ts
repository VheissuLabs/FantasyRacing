import { usePage } from '@inertiajs/vue3'

function getTimezone(): string {
    const page = usePage()
    return (page.props.timezone as string) || Intl.DateTimeFormat().resolvedOptions().timeZone
}

export function formatDate(value: string | Date | null | undefined): string {
    if (!value) return ''
    const date = typeof value === 'string' ? new Date(value) : value
    return date.toLocaleDateString('en-US', {
        timeZone: getTimezone(),
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    })
}

export function formatDateTime(value: string | Date | null | undefined): string {
    if (!value) return ''
    const date = typeof value === 'string' ? new Date(value) : value
    return date.toLocaleString('en-US', {
        timeZone: getTimezone(),
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    })
}

export function formatTime(value: string | Date | null | undefined): string {
    if (!value) return ''
    const date = typeof value === 'string' ? new Date(value) : value
    return date.toLocaleTimeString('en-US', {
        timeZone: getTimezone(),
        hour: 'numeric',
        minute: '2-digit',
    })
}

export function toLocalDatetimeValue(value: string | Date | null | undefined): string {
    if (!value) return ''
    const date = typeof value === 'string' ? new Date(value) : value
    const localized = new Date(date.toLocaleString('en-US', { timeZone: getTimezone() }))
    const year = localized.getFullYear()
    const month = String(localized.getMonth() + 1).padStart(2, '0')
    const day = String(localized.getDate()).padStart(2, '0')
    const hours = String(localized.getHours()).padStart(2, '0')
    const minutes = String(localized.getMinutes()).padStart(2, '0')
    return `${year}-${month}-${day}T${hours}:${minutes}`
}

export function useDate() {
    return { formatDate, formatDateTime, formatTime, toLocalDatetimeValue }
}
