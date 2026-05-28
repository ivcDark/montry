<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Новое обращение с сайта Montry</title>
</head>
<body style="margin:0;padding:0;background:#F6F8FB;color:#111827;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#F6F8FB;margin:0;padding:0;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;border-collapse:separate;">
                    <tr>
                        <td style="padding:0 0 18px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" valign="middle" style="width:44px;height:44px;border-radius:14px;background:#0F6BFF;color:#FFFFFF;font-size:20px;font-weight:800;line-height:44px;box-shadow:0 10px 28px rgba(15,107,255,0.18);">
                                        M
                                    </td>
                                    <td style="padding-left:12px;font-size:24px;line-height:30px;font-weight:800;color:#111827;">
                                        Montry
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="border:1px solid #E5E7EB;border-radius:28px;background:#FFFFFF;padding:32px;box-shadow:0 24px 64px rgba(15,23,42,0.10);">
                            <p style="margin:0 0 12px;font-size:13px;line-height:18px;font-weight:800;color:#12B3A8;text-transform:uppercase;letter-spacing:0;">
                                Обратная связь
                            </p>

                            <h1 style="margin:0;font-size:28px;line-height:36px;font-weight:800;color:#111827;letter-spacing:0;">
                                Новое обращение с главной страницы
                            </h1>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0;width:100%;">
                                <tr>
                                    <td style="border:1px solid #D8E6FF;border-radius:20px;background:#EAF2FF;padding:20px;">
                                        <p style="margin:0 0 6px;font-size:13px;line-height:20px;font-weight:800;color:#0F6BFF;">
                                            Контакт
                                        </p>
                                        <p style="margin:0;font-size:16px;line-height:26px;font-weight:800;color:#111827;">
                                            {{ $feedback->name }}
                                        </p>
                                        <p style="margin:4px 0 0;font-size:15px;line-height:24px;color:#667085;">
                                            {{ $feedback->email }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 10px;font-size:15px;line-height:24px;font-weight:800;color:#111827;">
                                Текст обращения
                            </p>
                            <p style="margin:0;font-size:16px;line-height:26px;font-weight:500;color:#667085;white-space:pre-line;">
                                {{ $feedback->message }}
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0 0;width:100%;">
                                <tr>
                                    <td style="border-radius:16px;background:#F8FAFC;padding:16px;">
                                        <p style="margin:0;font-size:13px;line-height:22px;color:#667085;">
                                            <strong style="color:#111827;">Страница:</strong> {{ $feedback->pageUrl ?? 'не указана' }}
                                        </p>
                                        <p style="margin:6px 0 0;font-size:13px;line-height:22px;color:#667085;">
                                            <strong style="color:#111827;">IP:</strong> {{ $feedback->ipAddress ?? 'не указан' }}
                                        </p>
                                        <p style="margin:6px 0 0;font-size:13px;line-height:22px;color:#667085;">
                                            <strong style="color:#111827;">User-Agent:</strong> {{ $feedback->userAgent ?? 'не указан' }}
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:20px 10px 0;">
                            <p style="margin:0;font-size:12px;line-height:20px;font-weight:600;color:#98A2B3;">
                                Это письмо отправлено формой обратной связи Montry.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
