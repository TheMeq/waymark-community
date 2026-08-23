<!DOCTYPE html>
<html lang="en">
<body style="margin:0;background:#f5f4ef;color:#252a24;font-family:Arial,sans-serif">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0"><tr><td align="center" style="padding:24px">
    <table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:100%;background:#ffffff;border:1px solid #deddd5;border-radius:16px">
        <tr><td style="padding:32px">
            <p style="margin:0 0 20px;color:#526b3f;font-weight:bold">{{ $copy['group_name'] }}</p>
            <h1 style="margin:0 0 16px;font-size:26px;line-height:1.25">{{ $copy['subject'] }}</h1>
            <p style="margin:0;line-height:1.6">{{ $copy['intro_text'] }}</p>
            @if (filled($copy['closing_text']))<p style="margin:24px 0 0;line-height:1.6;color:#596057">{{ $copy['closing_text'] }}</p>@endif
        </td></tr>
    </table>
</td></tr></table>
</body>
</html>
