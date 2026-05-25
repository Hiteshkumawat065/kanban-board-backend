<!doctype html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $companyName }}</title>
    <style>
        /* Reset */
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
        body { margin: 0 !important; padding: 0 !important; width: 100% !important; height: 100% !important; }

        body {
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen, Ubuntu, Cantarell,
                         "Helvetica Neue", sans-serif;
            color: #0f172a;
            line-height: 1.6;
        }

        .wrapper { width: 100%; background-color: #f1f5f9; padding: 32px 0; }
        .container { width: 100%; max-width: 600px; margin: 0 auto; background: #ffffff;
                     border-radius: 16px; overflow: hidden;
                     box-shadow: 0 4px 24px rgba(15, 23, 42, 0.08); }

        .header {
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            padding: 32px 32px 28px;
            color: #ffffff;
            text-align: left;
        }
        .header h1 { margin: 0; font-size: 22px; font-weight: 700; letter-spacing: -0.01em; }
        .header p { margin: 4px 0 0; font-size: 13px; opacity: 0.85; }

        .body { padding: 32px; font-size: 15px; color: #1e293b; }
        .body p { margin: 0 0 16px; }
        .body h1, .body h2, .body h3 { color: #0f172a; line-height: 1.3; margin: 0 0 12px; }
        .body h1 { font-size: 22px; }
        .body h2 { font-size: 18px; }
        .body h3 { font-size: 16px; }
        .body a { color: #4f46e5; }

        .btn {
            display: inline-block;
            background: #4f46e5;
            color: #ffffff !important;
            text-decoration: none;
            padding: 12px 22px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(79, 70, 229, 0.25);
        }

        .card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 16px 20px;
            margin: 16px 0;
        }

        .footer {
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 24px 32px;
            text-align: center;
            color: #64748b;
            font-size: 12px;
            line-height: 1.6;
        }
        .footer a { color: #4f46e5; text-decoration: none; }
        .socials { margin: 8px 0 12px; }
        .socials a { margin: 0 6px; color: #475569; text-decoration: none; font-weight: 600; font-size: 12px; }

        @media only screen and (max-width: 620px) {
            .container { border-radius: 0 !important; }
            .header, .body, .footer { padding-left: 20px !important; padding-right: 20px !important; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <table role="presentation" class="container" cellspacing="0" cellpadding="0" border="0">
            <tr>
                <td class="header">
                    <h1>{{ $companyName }}</h1>
                    <p>Stay focused. Get things done.</p>
                </td>
            </tr>
            <tr>
                <td class="body">
                    {!! $bodyHtml !!}
                </td>
            </tr>
            <tr>
                <td class="footer">
                    <div class="socials">
                        <a href="#">Twitter</a>·
                        <a href="#">LinkedIn</a>·
                        <a href="#">GitHub</a>
                    </div>
                    &copy; {{ $currentYear }} {{ $companyName }}. All rights reserved.<br>
                    You are receiving this email because you have an account with us.
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
