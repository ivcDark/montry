@extends('emails.layouts.base')

@section('title', 'Напоминание об оплате тарифа')

@section('content')
    <p style="margin:0 0 12px;font-size:12px;line-height:18px;font-weight:800;color:#E08600;text-transform:uppercase;letter-spacing:0;">
        Тариф
    </p>

    <h1 style="margin:0;font-size:28px;line-height:36px;font-weight:800;color:#26332D;letter-spacing:0;">
        Тариф заканчивается через {{ $daysUntilExpiration }} дн.
    </h1>

    <p style="margin:16px 0 0;font-size:16px;line-height:26px;color:#6B7D72;">
        В организации {{ $organizationName }} текущий тариф {{ $currentPlanName }} действует до {{ $expirationDate }}.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;width:100%;">
        <tr>
            <td style="border:1px solid #F6DCA8;border-radius:20px;background:#FFF8E8;padding:18px 20px;">
                <p style="margin:0;font-size:14px;line-height:22px;font-weight:800;color:#8A5200;">
                    @if ($upcomingPlanName)
                        Далее будет подключен тариф {{ $upcomingPlanName }}.
                    @else
                        Оплатите текущий тариф, чтобы мониторинги продолжили работать без ограничений Free.
                    @endif
                </p>
                @if ($upcomingPlanName)
                    <p style="margin:8px 0 0;font-size:14px;line-height:22px;color:#6B7D72;">
                        Чтобы он продолжил работать без перехода на Free после льготного периода, оплатите тариф.
                    </p>
                @endif
            </td>
        </tr>
    </table>

    <table role="presentation" cellpadding="0" cellspacing="0" style="margin:0;">
        <tr>
            <td style="border-radius:12px;background:#24A869;">
                <a href="{{ url('/billing') }}" style="display:inline-block;padding:13px 20px;font-size:14px;line-height:18px;font-weight:800;color:#FFFFFF;text-decoration:none;">
                    Открыть тарифы
                </a>
            </td>
        </tr>
    </table>
@endsection
