<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment confirmation — {{ config('app.name') }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f7f7f7;
            color: #333;
            padding: 20px;
            margin: 0;
        }
        .email-container {
            background-color: #fff;
            padding: 24px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08);
            max-width: 600px;
            margin: 0 auto;
        }
        h1 {
            color: #1a5276;
            font-size: 20px;
            margin-top: 0;
        }
        p {
            font-size: 16px;
            line-height: 1.6;
        }
        .amount {
            font-size: 18px;
            font-weight: bold;
            color: #1a5276;
        }
        .button {
            background-color: #1a5276;
            color: #ffffff !important;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 16px;
        }
        .meta {
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
<div class="email-container">
    <h1>{{ config('app.name') }}</h1>
    <p>Hello, {{ $name }}!</p>
    <p>Your bill payment was received successfully.</p>
    <p class="amount">Amount paid: ₦{{ number_format($amount, 2) }}</p>
    @if(!empty($assessmentNo))
        <p class="meta">Assessment number: <strong>{{ $assessmentNo }}</strong></p>
    @endif
    @if(!empty($receiptNo))
        <p class="meta">Receipt number: <strong>{{ $receiptNo }}</strong></p>
    @endif
    @if(!empty($payMode))
        <p class="meta">Payment channel: {{ $payMode }}</p>
    @endif
    @if(!empty($entryDate))
        <p class="meta">Payment date: {{ $entryDate }}</p>
    @endif
    <p>Download or view your receipt using the link below:</p>
    <p><a class="button" href="{{ $receiptUrl }}">View receipt</a></p>
    <p class="meta" style="word-break: break-all;">{{ $receiptUrl }}</p>
    <p>
        Thank you,<br>
        {{ config('mail.from.name', config('app.name')) }}
    </p>
</div>
</body>
</html>
