<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Montry')</title>
</head>
<body style="margin:0;padding:0;background:#F3F8F5;color:#26332D;font-family:Inter,-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#F3F8F5;margin:0;padding:0;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;max-width:@yield('containerWidth', '600px');border-collapse:separate;">
                    <tr>
                        <td style="padding:0 0 18px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" valign="middle" style="width:44px;height:44px;border-radius:14px;background:#123D2B;color:#FFFFFF;font-size:20px;font-weight:800;line-height:44px;box-shadow:0 12px 28px rgba(18,61,43,0.16);">
                                        M
                                    </td>
                                    <td style="padding-left:12px;font-size:24px;line-height:30px;font-weight:800;color:#173B2A;">
                                        Montry
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="border:1px solid @yield('cardBorder', '#DDEBE3');border-radius:24px;background:#FFFFFF;padding:32px;box-shadow:0 24px 60px rgba(31,68,49,0.10);">
                            @yield('content')
                        </td>
                    </tr>

                    <tr>
                        <td align="center" style="padding:20px 10px 0;">
                            <p style="margin:0;font-size:12px;line-height:20px;font-weight:600;color:#8A9A90;">
                                @yield('footer', 'Montry помогает следить за сайтами, SSL, доменами и важными техническими сроками.')
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
