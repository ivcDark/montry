@extends('emails.layouts.base')

@section('title', $feedback->source === 'account' ? 'Обращение из личного кабинета' : 'Новое обращение с сайта Montry')
@section('containerWidth', '620px')
@section('footer', 'Это письмо отправлено формой обратной связи Montry.')

@section('content')
    <p style="margin:0 0 12px;font-size:12px;line-height:18px;font-weight:800;color:#24A869;text-transform:uppercase;letter-spacing:0;">
        {{ $feedback->source === 'account' ? 'Техподдержка' : 'Обратная связь' }}
    </p>

    <h1 style="margin:0;font-size:28px;line-height:36px;font-weight:800;color:#26332D;letter-spacing:0;">
        {{ $feedback->source === 'account' ? 'Обращение из личного кабинета' : 'Новое обращение с главной страницы' }}
    </h1>

    @if($feedback->subject)
        <p style="margin:14px 0 0;font-size:16px;line-height:26px;font-weight:700;color:#52645A;">
            {{ $feedback->subject }}
        </p>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0;width:100%;">
        <tr>
            <td style="border:1px solid #BEE7CE;border-radius:20px;background:#E9F8EF;padding:20px;">
                <p style="margin:0 0 6px;font-size:13px;line-height:20px;font-weight:800;color:#1E9B5D;">
                    Контакт
                </p>
                <p style="margin:0;font-size:16px;line-height:26px;font-weight:800;color:#26332D;">
                    {{ $feedback->name }}
                </p>
                <p style="margin:4px 0 0;font-size:15px;line-height:24px;color:#52645A;">
                    {{ $feedback->email }}
                </p>
                @if($feedback->userId)
                    <p style="margin:12px 0 0;font-size:13px;line-height:22px;color:#52645A;">
                        <strong style="color:#26332D;">Пользователь:</strong>
                        #{{ $feedback->userId }} · {{ $feedback->userName ?? 'без имени' }} · {{ $feedback->userEmail ?? 'без email' }}
                    </p>
                @endif
                @if($feedback->organizationId)
                    <p style="margin:6px 0 0;font-size:13px;line-height:22px;color:#52645A;">
                        <strong style="color:#26332D;">Организация:</strong>
                        #{{ $feedback->organizationId }} · {{ $feedback->organizationName ?? 'без названия' }}
                    </p>
                @endif
            </td>
        </tr>
    </table>

    <p style="margin:0 0 10px;font-size:15px;line-height:24px;font-weight:800;color:#26332D;">
        Текст обращения
    </p>
    <p style="margin:0;font-size:16px;line-height:26px;font-weight:500;color:#6B7D72;white-space:pre-line;">
        {{ $feedback->message }}
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0 0;width:100%;">
        <tr>
            <td style="border-radius:16px;background:#F6FBF8;padding:16px;">
                <p style="margin:0;font-size:13px;line-height:22px;color:#6B7D72;">
                    <strong style="color:#26332D;">Страница:</strong> {{ $feedback->pageUrl ?? 'не указана' }}
                </p>
                <p style="margin:6px 0 0;font-size:13px;line-height:22px;color:#6B7D72;">
                    <strong style="color:#26332D;">IP:</strong> {{ $feedback->ipAddress ?? 'не указан' }}
                </p>
                <p style="margin:6px 0 0;font-size:13px;line-height:22px;color:#6B7D72;">
                    <strong style="color:#26332D;">User-Agent:</strong> {{ $feedback->userAgent ?? 'не указан' }}
                </p>
            </td>
        </tr>
    </table>
@endsection
