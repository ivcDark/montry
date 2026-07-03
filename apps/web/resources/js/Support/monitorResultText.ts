export type MonitorResult = {
    status: string
    response_time_ms: number | null
    status_code: number | null
    error_code: string | null
    error_message: string | null
    normalized_result: Record<string, unknown>
}

type ResultLike = MonitorResult & {
    check_type?: string
}

const HTTP_STATUS_MESSAGES: Record<number, string> = {
    400: 'Некорректный запрос',
    401: 'Требуется авторизация',
    403: 'Доступ запрещен',
    404: 'Страница или файл не найдены',
    408: 'Сервер слишком долго не отвечал',
    429: 'Слишком много запросов',
    500: 'Внутренняя ошибка сервера',
    502: 'Плохой ответ от вышестоящего сервера',
    503: 'Сервис временно недоступен',
    504: 'Истекло время ожидания ответа сервера',
}

const ERROR_CODE_MESSAGES: Record<string, string> = {
    timeout: 'Проверка превысила лимит времени',
    request_timeout: 'Проверка превысила лимит времени',
    context_deadline_exceeded: 'Проверка превысила лимит времени',
    dns_error: 'Не удалось найти DNS-запись для домена',
    dns_lookup_failed: 'Не удалось найти DNS-запись для домена',
    no_such_host: 'Домен не найден в DNS',
    connection_refused: 'Сервер отклонил подключение',
    connection_reset: 'Соединение было сброшено сервером',
    connection_failed: 'Не удалось подключиться к серверу',
    network_error: 'Сетевая ошибка при проверке',
    tls_error: 'Ошибка TLS/SSL-соединения',
    certificate_error: 'Проблема с SSL-сертификатом',
    ssl_error: 'Проблема с SSL-сертификатом',
    invalid_certificate: 'SSL-сертификат недействителен',
    whois_error: 'Не удалось получить данные WHOIS по домену',
    parse_error: 'Ответ получен, но его не удалось разобрать',
    invalid_xml: 'Файл найден, но XML некорректен',
}

export function dayWord(count: number): string {
    const mod10 = Math.abs(count) % 10
    const mod100 = Math.abs(count) % 100

    if (mod10 === 1 && mod100 !== 11) return 'день'
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'дня'

    return 'дней'
}

export function monitorResultText(type: string, result: ResultLike, fallbackStatusLabel: (status: string) => string): string {
    const technicalDetails = resultDetails(result)
    const errorText = localizedError(result)

    if (errorText) return withDetails(errorText, technicalDetails)

    if (['http', 'api_endpoint', 'robots_txt', 'sitemap_xml'].includes(type)) {
        return httpResultText(type, result, fallbackStatusLabel)
    }

    if (type === 'dns') return dnsResultText(result, fallbackStatusLabel)
    if (type === 'tcp_port') return tcpPortResultText(result, fallbackStatusLabel)
    if (type === 'ssl') return sslResultText(result, fallbackStatusLabel)
    if (type === 'domain') return domainResultText(result, fallbackStatusLabel)

    return fallbackStatusLabel(result.status)
}

function httpResultText(type: string, result: ResultLike, fallbackStatusLabel: (status: string) => string): string {
    const statusCode = result.status_code
    const details = resultDetails(result)

    if (type === 'sitemap_xml') {
        if (result.normalized_result.exists === false || statusCode === 404) {
            return withDetails('Sitemap.xml не найден', details)
        }

        if (result.normalized_result.valid_xml === false) {
            return withDetails('Sitemap.xml найден, но XML некорректен', details)
        }
    }

    if (type === 'robots_txt' && (result.normalized_result.exists === false || statusCode === 404)) {
        return withDetails('Robots.txt не найден', details)
    }

    if (typeof statusCode === 'number') {
        const statusMessage = HTTP_STATUS_MESSAGES[statusCode]

        if (statusCode < 200 || statusCode >= 400) {
            const subject = type === 'api_endpoint'
                ? 'API вернул ошибку'
                : type === 'robots_txt'
                    ? 'Robots.txt вернул ошибку'
                    : type === 'sitemap_xml'
                        ? 'Sitemap.xml вернул ошибку'
                        : 'Сайт вернул ошибку'

            return withDetails(`${subject}: ${statusMessage ?? `HTTP ${statusCode}`}`, details)
        }

        return withDetails(`Проверка прошла успешно: HTTP ${statusCode}`, result.response_time_ms ? `${result.response_time_ms} мс` : '')
    }

    return fallbackStatusLabel(result.status)
}

function dnsResultText(result: ResultLike, fallbackStatusLabel: (status: string) => string): string {
    const records = result.normalized_result.records
    const count = Array.isArray(records) ? records.length : 0
    const details = responseTimeDetails(result)

    if (result.normalized_result.resolved === false) return withDetails('DNS-записи не найдены', details)
    if (count > 0) return withDetails(`${count} ${dnsRecordWord(count)}`, details)

    return fallbackStatusLabel(result.status)
}

function tcpPortResultText(result: ResultLike, fallbackStatusLabel: (status: string) => string): string {
    if (result.normalized_result.open === true) {
        return withDetails('Порт открыт', responseTimeDetails(result))
    }

    if (result.normalized_result.open === false) {
        return withDetails('Порт закрыт или недоступен', responseTimeDetails(result))
    }

    return fallbackStatusLabel(result.status)
}

function sslResultText(result: ResultLike, fallbackStatusLabel: (status: string) => string): string {
    if (result.normalized_result.valid === false) return 'SSL-сертификат недействителен'

    const days = result.normalized_result.days_until_expiration

    if (typeof days === 'number') {
        if (days <= 0) return 'SSL-сертификат уже истек'

        return `SSL-сертификат действителен, до истечения ${days} ${dayWord(days)}`
    }

    return fallbackStatusLabel(result.status)
}

function domainResultText(result: ResultLike, fallbackStatusLabel: (status: string) => string): string {
    if (result.normalized_result.registered === false) return 'Домен не зарегистрирован или недоступен в WHOIS'

    const days = result.normalized_result.days_until_expiration

    if (typeof days === 'number') {
        if (days <= 0) return 'Срок регистрации домена истек'

        return `До истечения домена ${days} ${dayWord(days)}`
    }

    return fallbackStatusLabel(result.status)
}

function localizedError(result: ResultLike): string | null {
    const code = normalizeErrorCode(result.error_code)

    if (code && ERROR_CODE_MESSAGES[code]) return ERROR_CODE_MESSAGES[code]

    const message = result.error_message?.trim()

    if (!message) return null

    const translatedMessage = translateErrorMessage(message)

    if (translatedMessage) return translatedMessage
    if (typeof result.status_code === 'number') return null

    return 'Ошибка проверки: ' + message
}

function translateErrorMessage(message: string): string | null {
    const lower = message.toLowerCase()

    if (lower.includes('timeout') || lower.includes('deadline exceeded')) return 'Проверка превысила лимит времени'
    if (lower.includes('no such host') || lower.includes('dns')) return 'Не удалось найти DNS-запись для домена'
    if (lower.includes('connection refused')) return 'Сервер отклонил подключение'
    if (lower.includes('connection reset')) return 'Соединение было сброшено сервером'
    if (lower.includes('certificate') || lower.includes('tls') || lower.includes('ssl')) return 'Проблема с SSL-сертификатом или TLS-соединением'
    if (lower.includes('invalid xml') || lower.includes('parse')) return 'Ответ получен, но его не удалось разобрать'

    return null
}

function normalizeErrorCode(code: string | null): string | null {
    if (!code) return null

    return code.trim().toLowerCase().replaceAll('-', '_').replaceAll(' ', '_')
}

function resultDetails(result: ResultLike): string {
    const parts: string[] = []

    if (typeof result.status_code === 'number') parts.push(`HTTP ${result.status_code}`)
    if (result.response_time_ms) parts.push(`${result.response_time_ms} мс`)

    return parts.join(' · ')
}

function responseTimeDetails(result: ResultLike): string {
    return result.response_time_ms ? `${result.response_time_ms} мс` : ''
}

function withDetails(text: string, details: string): string {
    return details ? `${text} · ${details}` : text
}

function dnsRecordWord(count: number): string {
    const mod10 = count % 10
    const mod100 = count % 100

    if (mod10 === 1 && mod100 !== 11) return 'DNS-запись'
    if (mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14)) return 'DNS-записи'

    return 'DNS-записей'
}
