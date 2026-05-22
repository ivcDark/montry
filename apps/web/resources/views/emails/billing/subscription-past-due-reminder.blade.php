<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Необходимо оплатить тариф</title>
</head>
<body style="margin:0;padding:0;background:#F6F8FB;color:#111827;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#F6F8FB;margin:0;padding:0;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:560px;">
                    <tr>
                        <td style="border:1px solid #E5E7EB;border-radius:24px;background:#FFFFFF;padding:32px;box-shadow:0 20px 54px rgba(15,23,42,0.10);">
                            <p style="margin:0 0 12px;font-size:13px;line-height:18px;font-weight:800;color:#F97316;text-transform:uppercase;letter-spacing:0;">
                                Требуется оплата
                            </p>
                            <h1 style="margin:0;font-size:26px;line-height:34px;font-weight:800;color:#111827;letter-spacing:0;">
                                Тариф {{ $planName }} работает в льготном периоде
                            </h1>
                            <p style="margin:16px 0 0;font-size:16px;line-height:26px;color:#667085;">
                                В организации {{ $organizationName }} тариф не оплачен {{ $daysPastDue }} дн. Он продолжит работать до {{ $freeSwitchDate }}.
                            </p>
                            <p style="margin:12px 0 0;font-size:16px;line-height:26px;color:#667085;">
                                Если оплата не поступит, организация будет переключена на Free, а мониторинги сверх бесплатного лимита будут приостановлены.
                            </p>
                            <table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px 0 0;">
                                <tr>
                                    <td style="border-radius:14px;background:#0F6BFF;">
                                        <a href="{{ url('/billing') }}" style="display:inline-block;padding:13px 20px;font-size:14px;line-height:18px;font-weight:800;color:#FFFFFF;text-decoration:none;">
                                            Оплатить тариф
                                        </a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
