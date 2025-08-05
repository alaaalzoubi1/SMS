<!-- resources/views/emails/account_approved.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Account Has Been Approved</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #f4f7fc;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header {
            background-color: #4CAF50;
            color: white;
            padding: 10px 0;
            text-align: center;
            font-size: 24px;
        }

        .content {
            margin-top: 20px;
            font-size: 16px;
        }

        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 14px;
            color: #777;
        }

        .button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 20px;
        }

        .button:hover {
            background-color: #45a049;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        Sahtee
    </div>

    <div class="content">
        <h2>Hello {{ $account->full_name }},</h2>
        <p>We are excited to inform you that your account has been approved successfully. You can now log in to your account and explore our services.</p>

        <p>If you have any questions, feel free to contact us.</p>

        <a href="{{ url('/login') }}" class="button">Login to Your Account</a>
    </div>

    <div class="footer">
        <p>Thank you for choosing Sahtee!</p>
    </div>
</div>
</body>
</html>
