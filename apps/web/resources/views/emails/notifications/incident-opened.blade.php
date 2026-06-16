@extends('emails.layouts.base')

@section('title', $notification->subject)
@section('cardBorder', '#F7C7C7')

@section('content')
    <p style="margin:0 0 12px;font-size:12px;line-height:18px;font-weight:800;color:#D64545;text-transform:uppercase;letter-spacing:0;">
        Инцидент открыт
    </p>

    <h1 style="margin:0;font-size:28px;line-height:36px;font-weight:800;color:#26332D;letter-spacing:0;">
        {{ $notification->payload['title'] ?? $notification->subject }}
    </h1>

    <p style="margin:16px 0 0;font-size:16px;line-height:26px;font-weight:500;color:#6B7D72;">
        {{ $notification->payload['summary'] ?? $notification->body }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0;width:100%;">
        <tr>
            <td style="border:1px solid #F7C7C7;border-radius:20px;background:#FFF5F5;padding:20px;">
                <p style="margin:0 0 12px;font-size:15px;line-height:24px;font-weight:800;color:#26332D;">
                    Детали инцидента
                </p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#8A9A90;">Статус</td>
                        <td align="right" style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#D64545;">{{ $notification->payload['status'] ?? 'open' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#8A9A90;">Мониторинг</td>
                        <td align="right" style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#26332D;">#{{ $notification->payload['monitor_id'] ?? 'unknown' }}</td>
                    </tr>
                    <tr>
                        <td style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#8A9A90;">Начало</td>
                        <td align="right" style="padding:7px 0;font-size:13px;line-height:20px;font-weight:800;color:#26332D;">{{ $notification->payload['started_at'] ?? 'только что' }}</td>
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
