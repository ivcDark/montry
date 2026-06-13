<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Отчет по инцидентам за неделю</title>
</head>
<body style="margin:0;background:#F6F8FB;font-family:Arial,sans-serif;color:#111827;">
    <div style="max-width:720px;margin:0 auto;padding:32px 20px;">
        <div style="background:#ffffff;border:1px solid #E5E7EB;border-radius:16px;padding:28px;">
            <p style="margin:0 0 8px;color:#0F6BFF;font-size:14px;font-weight:700;">Montry</p>
            <h1 style="margin:0;color:#111827;font-size:26px;line-height:1.25;">Отчет по инцидентам за неделю</h1>
            <p style="margin:12px 0 0;color:#667085;font-size:15px;line-height:1.6;">
                {{ $organizationName }} · {{ $weekStart->format('d.m.Y') }} - {{ $weekEnd->format('d.m.Y') }}
            </p>

            <div style="margin-top:24px;padding:18px;border-radius:14px;background:#F6F8FB;">
                <p style="margin:0;color:#667085;font-size:14px;font-weight:700;">Всего инцидентов</p>
                <p style="margin:8px 0 0;color:#111827;font-size:34px;font-weight:800;">{{ $incidentCount }}</p>
            </div>

            @if ($incidentCount === 0)
                <p style="margin:24px 0 0;color:#16A34A;font-size:16px;font-weight:700;">
                    За прошлую неделю инцидентов не было.
                </p>
            @else
                <table style="width:100%;margin-top:24px;border-collapse:collapse;font-size:14px;">
                    <thead>
                        <tr>
                            <th align="left" style="padding:10px;border-bottom:1px solid #E5E7EB;color:#667085;">Сайт</th>
                            <th align="left" style="padding:10px;border-bottom:1px solid #E5E7EB;color:#667085;">Тип</th>
                            <th align="left" style="padding:10px;border-bottom:1px solid #E5E7EB;color:#667085;">Начался</th>
                            <th align="left" style="padding:10px;border-bottom:1px solid #E5E7EB;color:#667085;">Длительность</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($incidents as $incident)
                            <tr>
                                <td style="padding:10px;border-bottom:1px solid #E5E7EB;font-weight:700;">{{ $incident['site'] }}</td>
                                <td style="padding:10px;border-bottom:1px solid #E5E7EB;">{{ $incident['type'] }}</td>
                                <td style="padding:10px;border-bottom:1px solid #E5E7EB;">{{ $incident['started_at'] }}</td>
                                <td style="padding:10px;border-bottom:1px solid #E5E7EB;">{{ $incident['duration'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif

            <p style="margin:28px 0 0;">
                <a href="{{ url('/incidents') }}" style="display:inline-block;background:#0F6BFF;color:#ffffff;text-decoration:none;font-weight:800;border-radius:12px;padding:12px 18px;">
                    Открыть инциденты
                </a>
            </p>
        </div>
    </div>
</body>
</html>
