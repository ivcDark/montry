@extends('emails.layouts.base')

@section('title', 'Оплата прошла успешно')

@section('content')
    <p style="margin:0 0 12px;font-size:12px;line-height:18px;font-weight:800;color:#159653;text-transform:uppercase;letter-spacing:0;">
        Оплата подтверждена
    </p>

    <h1 style="margin:0;font-size:28px;line-height:36px;font-weight:800;color:#26332D;letter-spacing:0;">
        Тариф {{ $planName }} активирован
    </h1>

    <p style="margin:16px 0 0;font-size:16px;line-height:26px;color:#6B7D72;">
        Оплата для организации {{ $organizationName }} прошла успешно. Мониторинг уже работает с новыми лимитами.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;width:100%;border:1px solid #DDEBE3;border-radius:20px;background:#F8FCFA;">
        <tr>
            <td style="padding:18px 20px;border-bottom:1px solid #DDEBE3;font-size:14px;line-height:22px;color:#6B7D72;">
                Сумма
            </td>
            <td align="right" style="padding:18px 20px;border-bottom:1px solid #DDEBE3;font-size:16px;line-height:22px;font-weight:800;color:#173B2A;">
                {{ $amount }}
            </td>
        </tr>
        <tr>
            <td style="padding:18px 20px;font-size:14px;line-height:22px;color:#6B7D72;">
                Дата оплаты
            </td>
            <td align="right" style="padding:18px 20px;font-size:14px;line-height:22px;font-weight:800;color:#26332D;">
                {{ $paidAt }}
            </td>
        </tr>
    </table>

    @if (count($items) > 0)
        <p style="margin:0 0 10px;font-size:14px;line-height:22px;font-weight:800;color:#26332D;">
            Дополнительные возможности
        </p>

        @foreach ($items as $item)
            <p style="margin:0 0 6px;font-size:14px;line-height:22px;color:#6B7D72;">
                {{ $item['name'] }} × {{ $item['quantity'] }} — {{ $item['amount'] }}
            </p>
        @endforeach
    @endif

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 0;">
        <tr>
            <td style="border-radius:12px;background:#24A869;">
                <a href="{{ url('/sites') }}" style="display:inline-block;padding:13px 20px;font-size:14px;line-height:18px;font-weight:800;color:#FFFFFF;text-decoration:none;">
                    Перейти к сайтам
                </a>
            </td>
        </tr>
    </table>
@endsection
