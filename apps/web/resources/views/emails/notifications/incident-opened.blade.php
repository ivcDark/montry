<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $notification->subject }}</title>
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
                                        Montri
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="border:1px solid #FECACA;border-radius:28px;background:#FFFFFF;padding:32px;box-shadow:0 24px 64px rgba(15,23,42,0.10);">
                            <p style="margin:0 0 12px;font-size:13px;line-height:18px;font-weight:800;color:#EF4444;text-transform:uppercase;letter-spacing:0;">
                                Инцидент открыт
                            </p>

                            <h1 style="margin:0;font-size:28px;line-height:36px;font-weight:800;color:#111827;letter-spacing:0;">
                                {{ $notification->payload['title'] ?? $notification->subject }}
                            </h1>

                            <p style="margin:16px 0 0;font-size:16px;line-height:26px;font-weight:500;color:#667085;">
                                {{ $notification->payload['summary'] ?? $notification->body }}
                            </p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0;width:100%;">
                                <tr>
                                    <td style="border:1px solid #FECACA;border-radius:20px;background:#FFF8F8;padding:20px;">
                                        <p style="margin:0 0 12px;font-size:15px;line-height:24px;font-weight:800;color:#111827;">
                                            Детали инцидента
                                        </p>
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="padding:6px 0;font-size:13px;line-height:20px;font-weight:800;color:#98A2B3;">Статус</td>
                                                <td align="right" style="padding:6px 0;font-size:13px;line-height:20px;font-weight:800;color:#EF4444;">{{ $notification->payload['status'] ?? 'open' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0;font-size:13px;line-height:20px;font-weight:800;color:#98A2B3;">Мониторинг</td>
                                                <td align="right" style="padding:6px 0;font-size:13px;line-height:20px;font-weight:800;color:#111827;">#{{ $notification->payload['monitor_id'] ?? 'unknown' }}</td>
                                            </tr>
                                            <tr>
                                                <td style="padding:6px 0;font-size:13px;line-height:20px;font-weight:800;color:#98A2B3;">Начало</td>
                                                <td align="right" style="padding:6px 0;font-size:13px;line-height:20px;font-weight:800;color:#111827;">{{ $notification->payload['started_at'] ?? 'только что' }}</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0;">
                                <tr>
                                    <td style="border-radius:14px;background:#0F6BFF;">
                                        <a href="{{ url('/dashboard') }}" style="display:inline-block;padding:13px 20px;font-size:14px;line-height:18px;font-weight:800;color:#FFFFFF;text-decoration:none;">
                                            Открыть кабинет
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:20px 10px 0;">
                            <p style="margin:0;font-size:12px;line-height:20px;font-weight:600;color:#98A2B3;">
                                Montri помогает следить за сайтами, SSL и доменами.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
