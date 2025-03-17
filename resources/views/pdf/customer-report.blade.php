<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Statement Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 5px;
            background: url({{ asset('assets/images/top-background.png') }});
            height: 210px; background-repeat: no-repeat;
            background-position: center center;
            background-size: contain;
        }
        table {
            width: 95%;
            margin: auto;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 5px;
            text-align: left;
            font-size: 12px;
        }
        th {
            color: #1a202c;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="header">
</div>

<table style="border: none !important; margin-top: -40px;">
    <tr>
        <td style="width: 150px;"><strong>Payer ID:</strong></td>
        <td>{{$headerInfo['payerId'] ?? '' }}</td>
    </tr>
    <tr>
        <td style="width: 150px;"><strong>Building Code:</strong></td>
        <td style="text-transform: uppercase;">{{$headerInfo['buildingCode'] ?? '' }}</td>
    </tr>
    <tr>
        <td style="width: 150px;"><strong>Address:</strong></td>
        <td>{{$headerInfo['address'] ?? '' }}</td>
    </tr>
    <tr>
        <td style="width: 150px;"><strong>Customer Statement Report From:</strong></td>
        <td>{{$headerInfo['from'] ?? '' }} <span style="color: #ff0000;">to</span> {{$headerInfo['to'] ?? '' }}</td>
    </tr>
</table>

<br>


<table>
    <tr style="font-size: 12px !important;">
        <th style="font-size: 12px !important;">#</th>
        <th style="font-size: 12px !important;">Date</th>
        <th style="text-align: right; font-size: 12px !important;">(NGN) Amount</th>
        <th style="font-size: 12px !important;">Channel</th>
        <th style="font-size: 12px !important;">Receipt</th>
        <th style="font-size: 12px !important;">Narration</th>
    </tr>
    @foreach($records as $record)
    <tr>
        <td>{{$record['serial'] }}</td>
        <td>{{$record['date']}}</td>
        <td style="text-align: right;">{{$record['amount'] ?? 0}}</td>
        <td>{{$record['channel'] ?? '' }}</td>
        <td>{{ $record['receipt'] ?? '' }}</td>
        <td>{{ $record['narration'] ?? '' }}</td>
    </tr>
    @endforeach
</table>

</body>
</html>
