@extends('emails.layouts.base')

@section('title', $notification->subject)
@section('cardBorder', '#BDE8D1')

@section('content')
    <p style="margin:0 0 12px;font-size:12px;line-height:18px;font-weight:800;color:#24A869;text-transform:uppercase;letter-spacing:0;">
        Мониторинг восстановлен
    </p>

    <h1 style="margin:0;font-size:28px;line-height:36px;font-weight:800;color:#26332D;letter-spacing:0;">
        {{ $notification->payload['resource_name'] ?? $notification->subject }}
    </h1>

    <p style="margin:16px 0 0;font-size:16px;line-height:26px;font-weight:500;color:#6B7D72;">
        Проверка снова проходит успешно. Инцидент закрыт.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0;width:100%;">
        <tr>
            <td style="border:1px solid #BDE8D1;border-radius:20px;background:#F2FBF6;padding:20px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#8A9A90;">Адрес</td>
                        <td align="right" style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#26332D;">{{ $notification->payload['target'] ?? 'не указан' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#8A9A90;">Тип мониторинга</td>
                        <td align="right" style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#26332D;">{{ $notification->payload['monitor_type_label'] ?? 'Мониторинг' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#8A9A90;">Восстановлен</td>
                        <td align="right" style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#24A869;">{{ $notification->payload['resolved_at_formatted'] ?? 'только что' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#8A9A90;">Длительность сбоя</td>
                        <td align="right" style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#26332D;">{{ $notification->payload['duration_formatted'] ?? 'неизвестно' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0;">
        <tr>
            <td style="border-radius:12px;background:#24A869;">
                <a href="{{ url('/dashboard') }}" style="display:inline-block;padding:13px 20px;font-size:14px;line-height:18px;font-weight:800;color:#FFFFFF;text-decoration:none;">
                    Открыть кабинет
                </a>
            </td>
        </tr>
    </table>
@endsection
