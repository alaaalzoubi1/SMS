<!-- resources/views/emails/account_approved.blade.php -->
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تمت الموافقة على حسابك</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            color: #333;
            direction: rtl;
            text-align: right;
            margin: 0;
            padding: 20px;
            background-color: #ffffff;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #f4f7fc;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            direction: rtl;
            text-align: right;
        }

        .header {
            background-color: teal;
            color: white;
            padding: 12px 0;
            text-align: center;
            font-size: 24px;
            border-radius: 6px;
        }

        .content {
            margin-top: 20px;
            font-size: 16px;
            line-height: 1.9;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 14px;
            color: #777;
        }
    </style>
</head>
<body dir="rtl">
<div class="container" dir="rtl">
    <div class="header">
        معافى
    </div>

    <div class="content" dir="rtl">
        <h2>مرحباً{{ $name ? ' ' . $name : '' }}،</h2>
        <p>يسعدنا إعلامك بأنه تمت الموافقة على حسابك بنجاح. يمكنك الآن تسجيل الدخول إلى حسابك والبدء باستخدام خدماتنا.</p>

        <p>إذا كان لديك أي استفسار، لا تتردد في التواصل معنا.</p>
    </div>

    <div class="footer" dir="rtl">
        <p>شكراً لاختيارك معافى!</p>
        <p>معافى يتمنى لكم الشفاء</p>
    </div>
</div>
</body>
</html>
