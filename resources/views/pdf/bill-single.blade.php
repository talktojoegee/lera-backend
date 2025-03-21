<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Land Use Charge Assessment  </title>
    <style>
        @page {
            margin: 1mm;
        }
        body {
            font-family: 'inter', sans-serif;
            background-image: url('{{ asset('/assets/images/background.png') }}');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: contain;
            font-size: 13px;
        }
        .formatted-top{
            margin-top: 210px;
        }
        .table {
            border-collapse: separate;
            width: 95%;
            margin: 20px;
            border: 2px solid black;
            border-radius: 20px;
            overflow: hidden;
        }

        .table-2 {
            border-collapse: separate;
            width: 95%;
            margin: 20px;
            border: 2px solid black;
            overflow: hidden;
        }

        th {
            border-bottom: 2px solid black;
            text-align: left;
        }

        th, td {
            padding: 5px;
            text-align: left;
            vertical-align: top;
        }

        th:first-child, td:first-child {
            border-left: none;
        }

        th:last-child, td:last-child {
            border-right: none;
        }

        tr:last-child th, tr:last-child td {
            border-bottom: none;
        }
    </style>
</head>

    <body>

        <div class="formatted-top"></div>
        <table class="table">
            <tr>
                <th colspan="2" style="text-align: center; font-size: 15px">PROPERTY/OWNER DETAILS:</th>
            </tr>
            <tr>
                <td><strong>PROPERTY ID:</strong> <br> <span style="text-transform: uppercase;">{{$record->buildingCode ?? ''}}</span> </td>
                <td><strong>DATE:</strong> {{ isset($record->entryDate) ? date('d/m/Y', strtotime($record->entryDate)) : '-'}}</td>
            </tr>
            <tr>
                {{--<td><strong>BILL TO:</strong> <br>$record->propertyName ?? 'Property Owner'}} </td>--}}
                <td><strong>BILL TO:</strong> <br>Property Owner {!!  $record->class_id == 2 ? "<br>c/o  " .$record->propertyName : "" !!}</td>
                <td><strong>ASSESSMENT NO:</strong> <br> <span style="text-transform: uppercase;">{{$record->assessmentNo ?? ''}}</span> </td>
            </tr>
            <tr>
                <td> {{$record->propertyAddress ?? '' }}<br> {{$record->address ?? '' }}</td>
                <td><strong>PROPERTY ADDRESS:</strong> <br> {{$record->propertyAddress ?? '' }}</td>
            </tr>
            <tr>
                <td><strong>PROPERTY CLASSIFICATION:</strong> <br>{{$record->className ?? ''}}</td>
                <td><strong>KGTIN:</strong> {{$record->kgTin ?? ''}}<br>
                    <strong>PHONE:</strong> {{$record->mobileNo ?? ''}}
                </td>
            </tr>
        </table>


        <table class="table-2">
            <tr>
                <th colspan="3" style="text-align: center; font-size: 15px">{{ $record->year ?? ''  }} LAND USE CHARGE (LUC) OF PROPERTY</th>
            </tr>
            <tr>
                <th width="33%" style="border-right: 2px solid black;text-align: center">ASSESSED VALUE (NGN)</th>
                <th width="33%" style="border-right: 2px solid black;text-align: center">CHARGE RATE (%)</th>
                <th width="33%" style="text-align: center">LUC AMOUNT(NGN)</th>
            </tr>
            <tr style="border-bottom: 2px solid black;">
                <td width="33%" style="text-align: center;border-bottom: 2px solid black;font-size: 14px"><strong>{{ number_format($record->assessedValue,2) }}</strong></td>
                <td width="33%" style="text-align: center;border-bottom: 2px solid black;font-size: 14px"><strong>{{ $record->chargeRate }}</strong></td>
                <td width="33%" style="text-align: center;border-bottom: 2px solid black;font-size: 14px"><strong>{{ number_format($record->billAmount) }}</strong></td>
            </tr>
            <tr>
                <td colspan="2" style="font-size: 15px"><strong>ANNUAL LAND USE CHARGE {{ $record->year  }}</strong></td>
                <td style="font-size: 14px"><strong>{{ number_format($record->billAmount) }}</strong></td>
            </tr>
            <tr>
                <td colspan="2" style="font-size: 15px"><strong>TOTAL AMOUNT PAYABLE</strong></td>
                <td style="font-size: 14px"><strong>{{ number_format($record->billAmount) }}</strong></td>
            </tr>
            <tr>
                <td style="font-size: 11px"><strong>REVENUE CODE: 12020908</strong></td>
                <td></td>
            </tr>
        </table>

        <p style="margin-left: 20px; margin-top: -20px;"><strong>AMOUNT IN WORDS:</strong>  <span style="text-transform: uppercase;">{{ numberToWords($record->billAmount ?? 0 )}}</span> ONLY</p>

    </body>

</html>

