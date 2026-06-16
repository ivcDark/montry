@extends('emails.layouts.base')

@section('title', 'Отчет по инцидентам за неделю')
@section('containerWidth', '720px')

@section('content')
    <p style="margin:0 0 12px;font-size:12px;line-height:18px;font-weight:800;color:#24A869;text-transform:uppercase;letter-spacing:0;">
        Еженедельный отчет
    </p>

    <h1 style="margin:0;font-size:28px;line-height:36px;font-weight:800;color:#26332D;letter-spacing:0;">
        Отчет по инцидентам за неделю
    </h1>

    <p style="margin:12px 0 0;color:#6B7D72;font-size:15px;line-height:24px;">
        {{ $organizationName }} · {{ $weekStart->format('d.m.Y') }} - {{ $weekEnd->format('d.m.Y') }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;width:100%;">
        <tr>
            <td style="border:1px solid #DDEBE3;border-radius:20px;background:#F6FBF8;padding:18px 20px;">
                <p style="margin:0;color:#6B7D72;font-size:14px;font-weight:700;">Всего инцидентов</p>
                <p style="margin:8px 0 0;color:#173B2A;font-size:34px;line-height:40px;font-weight:800;">{{ $incidentCount }}</p>
            </td>
        </tr>
    </table>

    @if ($incidentCount === 0)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 24px;width:100%;">
            <tr>
                <td style="border:1px solid #BEE7CE;border-radius:20px;background:#E9F8EF;padding:18px 20px;">
                    <p style="margin:0;color:#1E9B5D;font-size:16px;line-height:24px;font-weight:800;">
                        За прошлую неделю инцидентов не было.
                    </p>
                </td>
            </tr>
        </table>
    @else
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;margin:0 0 24px;border-collapse:collapse;font-size:14px;">
            <thead>
                <tr>
                    <th align="left" style="padding:10px 8px;border-bottom:1px solid #DDEBE3;color:#6B7D72;font-size:12px;line-height:18px;text-transform:uppercase;">Сайт</th>
                    <th align="left" style="padding:10px 8px;border-bottom:1px solid #DDEBE3;color:#6B7D72;font-size:12px;line-height:18px;text-transform:uppercase;">Тип</th>
                    <th align="left" style="padding:10px 8px;border-bottom:1px solid #DDEBE3;color:#6B7D72;font-size:12px;line-height:18px;text-transform:uppercase;">Начался</th>
                    <th align="left" style="padding:10px 8px;border-bottom:1px solid #DDEBE3;color:#6B7D72;font-size:12px;line-height:18px;text-transform:uppercase;">Длительность</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($incidents as $incident)
                    <tr>
                        <td style="padding:12px 8px;border-bottom:1px solid #E8F0EB;color:#26332D;font-weight:800;">{{ $incident['site'] }}</td>
                        <td style="padding:12px 8px;border-bottom:1px solid #E8F0EB;color:#52645A;">{{ $incident['type'] }}</td>
                        <td style="padding:12px 8px;border-bottom:1px solid #E8F0EB;color:#52645A;">{{ $incident['started_at'] }}</td>
                        <td style="padding:12px 8px;border-bottom:1px solid #E8F0EB;color:#52645A;">{{ $incident['duration'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0;">
        <tr>
            <td style="border-radius:12px;background:#24A869;">
                <a href="{{ url('/incidents') }}" style="display:inline-block;padding:13px 20px;font-size:14px;line-height:18px;font-weight:800;color:#FFFFFF;text-decoration:none;">
                    Открыть инциденты
                </a>
            </td>
        </tr>
    </table>
@endsection
