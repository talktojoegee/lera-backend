<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Objection Update</title>
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
    <h1>Hello, {{ $name }}!</h1>
    <p>Just to update you on what we've done since you first raised an objection.</p>
    <p>Your bill with request ID of <strong>{{$requestId}}</strong> was <code>{{$status}}.</code> </p>
    <p>We thought you'll find this information helpful.</p>
    <p>If you have any questions, feel free to <a href="mailto:info@kslas.com">contact us</a>.</p>
    <p>
        Sincerely yours, <br>
        {{env('APP_TEAM', 'KSLAS Team')}}
    </p>
</div>
</body>
</html>
