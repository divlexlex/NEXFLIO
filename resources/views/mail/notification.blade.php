<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
</head>
<body style="margin:0; padding:0; background:#F7ECE1; font-family: Arial, Helvetica, sans-serif; color:#3D2817;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7ECE1; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="560" cellpadding="0" cellspacing="0"
                       style="background:#ffffff; border:1px solid #E8D5C4; border-radius:12px; overflow:hidden;">
                    <tr>
                        <td style="background:#3D2817; padding:20px 32px;">
                            <span style="color:#ffffff; font-size:20px; font-weight:bold;">
                                Perfect <span style="color:#E8B4B8;">Nails</span>
                            </span>
                            <span style="color:#ffffff; opacity:.6; font-size:12px;"> · Wellness &amp; Aesthetics</span>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;">
                            <h1 style="margin:0 0 16px; font-size:20px; color:#3D2817;">{{ $title }}</h1>
                            @foreach($lines as $line)
                                <p style="margin:0 0 12px; font-size:14px; line-height:1.6; color:#3D2817;">{{ $line }}</p>
                            @endforeach
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:16px 32px; background:#F7ECE1; border-top:1px solid #E8D5C4;">
                            <p style="margin:0; font-size:11px; color:#9C7A54;">
                                Perfect Nails Wellness and Aesthetics · Powered by NEXFLIO<br>
                                This is an automated message — replies are not monitored.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
