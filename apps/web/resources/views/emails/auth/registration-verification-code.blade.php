@extends('emails.layouts.base')

@section('title', 'Код подтверждения Montry')

@section('content')
    <p style="margin:0 0 12px;font-size:12px;line-height:18px;font-weight:800;color:#24A869;text-transform:uppercase;letter-spacing:0;">
        Подтверждение email
    </p>

    <h1 style="margin:0;font-size:28px;line-height:36px;font-weight:800;color:#26332D;letter-spacing:0;">
        Введите одноразовый код
    </h1>

    <p style="margin:16px 0 0;font-size:16px;line-height:26px;font-weight:500;color:#6B7D72;">
        Используйте этот код, чтобы завершить регистрацию и открыть личный кабинет Montry.
    </p>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:28px 0;width:100%;">
        <tr>
            <td align="center" style="border:1px solid #BEE7CE;border-radius:20px;background:#E9F8EF;padding:24px 16px;">
                <p style="margin:0 0 10px;font-size:13px;line-height:18px;font-weight:800;color:#1E9B5D;">
                    Код подтверждения
                </p>
                <p style="margin:0;font-size:38px;line-height:46px;font-weight:800;color:#173B2A;letter-spacing:6px;">
                    {{ $code }}
                </p>
            </td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;">
        <tr>
            <td style="border-radius:16px;background:#F6FBF8;padding:16px;">
                <p style="margin:0;font-size:14px;line-height:22px;font-weight:700;color:#26332D;">
                    Код действует 10 минут.
                </p>
                <p style="margin:6px 0 0;font-size:14px;line-height:22px;color:#6B7D72;">
                    Если вы не регистрировались в Montry, просто проигнорируйте это письмо.
                </p>
            </td>
        </tr>
    </table>
@endsection
