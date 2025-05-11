<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kogi State Revenue Receipt</title>
    <style>
        body {
            background: url({{asset('/assets/images/receipt-bg.jpg')}}) no-repeat center center;
            background-size: contain;
            font-family: Arial, sans-serif;
            font-size: 14px;
            margin: 0;
            padding: 0;

        }
        .formatted{
            margin-top: 210px;
        }
        .container {
            width: 800px;
            margin: 50px auto;
            padding: 50px;
            position: relative;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        tr{
            padding: 0;
        }
        td {
            padding: 4px;
            vertical-align: top;
            font-size: 12px;
        }
        .qr {
            width: 150px;
            height: 150px;
        }
        .bold {
            font-weight: bold;
        }
        .right {
            text-align: left;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="formatted"></div>
    <table >
        <tr>
            <td style="width: 20%;">
                <img style="width: 94px; height: 94px;" src="{{$qr}}" >
            </td>
            <td style="text-align: center; width: 40%;">
                <h2 style="line-height: 15px; margin: 2px 0;">KOGI STATE GOVERNMENT</h2>
                <h3 style="color: red; line-height: 15px; margin: 2px 0;">REVENUE RECEIPT</h3>
            </td>
            <td class="right" style="width: 40%;">
                <p style="text-transform: uppercase; line-height: 15px; margin: 2px 0;"><b>ASSESSMENT NO:</b> <br> {{$assessmentNo}}</p>
                <p style="text-transform: uppercase; line-height: 15px; margin: 2px 0;"><b>PROPERTY ID:</b> <br>{{$propertyId}}</p>
                <p style="text-transform: uppercase; line-height: 15px; margin: 2px 0;"><b>PAYMENT DATE:</b> <br> {{$payment_date}}</p>
            </td>
        </tr>
    </table>
    <table style="">
        <tr><td class="bold">KGTIN:</td><td style="text-transform: uppercase;">{{$payer_id}}</td></tr>
        <tr><td class="bold">Paid By:</td><td>{{$paid_by}}</td></tr>
        <tr><td class="bold">Address:</td><td>{{$address}}</td></tr>
        <tr><td class="bold">Amount:</td><td>{{$amount}} ({{$amount_words}} Only)</td></tr>
        <tr><td class="bold">Being:</td><td>{{$payment_purpose}}</td></tr>
        <tr><td class="bold">Paid At:</td>
            <td style="word-wrap: break-word;">{{$pay_mode}}({{$bankName}}) // Receipt No: {{$receipt_no}}
                <br>  // Invoice No: <span style="text-transform: uppercase;">{{$assessmentNo}}</span>
                <br>Swift Code: <span style="text-transform: uppercase;">{{$swift_code}}</span>// Paid Date:
                {{$payment_date}}</td>
        </tr>
        <tr><td class="bold">Agency:</td><td><span style="text-transform: uppercase;">Bureau for Lands and Urban Development</span></td></tr>
        <tr><td class="bold">Tax Station:</td><td style="text-transform: uppercase;">{{$tax_station}}</td></tr>
    </table>
</div>
</body>
</html>
