<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Creation</title>
    <style>
        /* You can add your custom styles here */
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            color: #333;
            padding: 20px;
        }

        .email-container {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }

        h1 {
            color: #2c3e50;
        }

        p {
            font-size: 16px;
            line-height: 1.6;
        }

        .button {
            background-color: #3498db;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
            display: inline-block;
        }
    </style>
</head>
<body>
<div class="email-container">
    <img src="/assets/logo/log.png" alt="" class="rounded-circle" height="74" width="74">
    <h4>Hello, {{ $name }}!</h4>
    <p>An account was created for you on our platform(<strong>{{env("APP_NAME")}}</strong>). Your login credentials are as presented below:</p>
    <p>Username<strong>: {{$username}}</strong> </p>
    <p>Password<strong>: {{$plainPassword}}</strong> </p>
    <p>Role<strong>: {{$roleName}}</strong> </p>
    <p>URL: <strong><a href="#">{{env('APP_ADMIN_LOGIN')}}</a></strong></p>
    <p>Do well to change your password upon successful login.</p>
    <p>
        Sincerely yours, <br>
        {{env('APP_TEAM', 'KSLAS Team')}}
    </p>
</div>
</body>
</html>
