@extends('emails.layouts.base')

@section('title', 'Регистрация в Montry завершена')

@section('content')
    <p style="margin:0 0 12px;font-size:12px;line-height:18px;font-weight:800;color:#24A869;text-transform:uppercase;letter-spacing:0;">
        Аккаунт готов
    </p>

    <h1 style="margin:0;font-size:28px;line-height:36px;font-weight:800;color:#26332D;letter-spacing:0;">
        {{ $userName }}, регистрация завершена
    </h1>

    <p style="margin:16px 0 0;font-size:16px;line-height:26px;font-weight:500;color:#6B7D72;">
        Мы создали личный кабинет, организацию и стартовый проект. Теперь можно добавить первый сайт и включить мониторинг доступности, SSL и домена.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0;width:100%;">
        <tr>
            <td style="border:1px solid #DDEBE3;border-radius:20px;background:#F6FBF8;padding:20px;">
                <p style="margin:0;font-size:15px;line-height:24px;font-weight:800;color:#26332D;">
                    С чего начать
                </p>
                <p style="margin:10px 0 0;font-size:14px;line-height:22px;color:#6B7D72;">
                    Добавьте сайт, проверьте настройки HTTP/SSL/домена и подключите удобные уведомления.
                </p>
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

    <p style="margin:22px 0 0;font-size:13px;line-height:20px;color:#8A9A90;">
        Если кнопка не открывается, перейдите в кабинет вручную: {{ url('/dashboard') }}
    </p>
@endsection
