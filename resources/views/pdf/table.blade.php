<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Land Use Charge Assessment</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <style>
        footer {
            width: 250mm;
            margin: 0px auto 0 auto;
        }
        table{
            background: transparent;
        }
        .bill-container {
            width: 100%;
            min-height: 297mm;
            /*margin: auto;*/
           /* padding: 8mm 5mm 0 5mm;*/

        }
        .header-logo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .header-logo img {
            height: 82px;
        }
        .title-section {
            text-align: center;
        }
        .title-section h2, h3, h4, h5 {
            margin: 0;
        }
        .table-bordered th, .table-bordered td {
           /* border: 1px solid black;*/
            padding: 10px;
            text-align: center;
        }
        .property-details h3, .property-details h5 {
            margin: 5px 0;
        }
        .general-info {
            font-size: 18px;
            font-weight: bold;
        }
/*        @media print {
            .bill-container {
                box-shadow: none;
                page-break-after: always;
            }
        }*/
        @media print {
            .bill-container {
                background: url('assets/images/kogi-watermark.png') no-repeat center center !important;
                background-size: cover !important;
                -webkit-print-color-adjust: exact; /* Ensures colors are printed */
                print-color-adjust: exact; /* Standard property */
            }
        }
        .table-tr{
            color: #030202;
            border-bottom: 1px solid #000000;
            font-size: 14px;

        }
        .table-trr{
            border: 1px solid #000; padding: 1px;
        }
        .table{
            background: none !important;
            margin: auto;
        }
        .page-break {
            page-break-before: always;
        }
       /* body {
            margin: 0;
            padding: 0;
            text-align: center; !* Centers content *!
        }

        td, th {
            text-align: center; !* Centers text *!
        }*/
    </style>
</head>
<body >
    @foreach($records as $record)
        <div class="bill-container" style="background: url({{asset('assets/images/kogi-watermark.png')}}) no-repeat center center; background-size: cover;
            box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
            position: relative;" >
            <table style="width: 100%; border-collapse: collapse; text-align: center;">
                <tr>
                    <td style="width: 20%; text-align: left;">
                        <img src="{{asset('assets/images/kgirs.png')}}" alt="KGIRS Logo" style="height: 80px;">
                    </td>
                    <td style="width: 60%;">
                        <h2 style="color: #13331A; font-size: 22px; margin: 5px 0;">KOGI STATE GOVERNMENT</h2>
                        <h3 style="color: #3870C2; font-size: 18px; margin: 5px 0;">KOGI STATE INTERNAL REVENUE SERVICE</h3>
                        <h4 style="color: #181518; font-size: 14px; margin: 5px 0;">NO. 1 BEACH ROAD, LOKOJA, KOGI STATE, NIGERIA.</h4>
                        <h5 style="color: #181518; font-size: 14px; margin: 5px 0;">PHONE: 08083427276</h5>
                    </td>
                    <td style="width: 20%; text-align: right;">
                        <img src="{{asset('assets/images/log.png')}}" alt="KGIRS Logo" style="height: 80px;">
                    </td>
                </tr>
            </table>

            <table class="table table-bordered"  >
                <tr>
                    <th colspan="3" style="background: #0B3622; font-size: 12px;" class=" text-white">LAND USE CHARGE ASSESSMENT
                        {{$record->year ?? ''}}</th>
                </tr>
            </table>
            <p style="font-size: 14px; display: block; margin-top: -2px;">In line with the provisions of Kogi State Land Use Charge Law 2024 and other matters connected therewith, we hereby present to you/your organization due obligation to the State in respect of the Revenue stated below.</p>

            <div class="property-details mt-1" style=" margin-top: -10px !important;">
                <table class="" style="width: 100%; background: transparent !important;
                border: 1px solid #000000; border-radius: 50px !important;">
                    <tr>
                        <td class="table-trr" colspan="4" style="font-size: 14px; font-weight: 500; text-transform: uppercase; text-align: center;">
                            Property/Owner Details
                        </td>
                    </tr>
                    <tr>
                        <th style="text-transform: uppercase; font-size: 14px; text-align: left; ">Property ID:
                            <span style="font-size: 12px; font-weight: 300;">{{$record->building_code ?? ''}} </span>
                        </th>
                        <td colspan="2" style="width: 200px;"></td>
                        <th style="text-transform: uppercase; font-size: 14px; text-align: left; ">Date:
                            <span style="font-size: 12px; font-weight: 300;">{{ date('d M, Y', strtotime($record->entry_date)) ?? ''}} </span>
                        </th>
                    </tr>
                    <tr>
                        <th style="text-transform: uppercase; font-size: 14px; text-align: left; ">Bill To: <br>
                            <span style="font-size: 12px; font-weight: 300;">{{$record->property_name ?? ''}} {{$record->owner_name ?? ''}}</span>
                        </th>
                        <td colspan="2"></td>
                        <th style="text-transform: uppercase; font-size: 14px; text-align: left; ">Assessment No.:
                            <span style="font-size: 12px; font-weight: 300;">{{$record->assessment_no ?? ''}}</span>
                        </th>
                    </tr>
                    <tr>
                        <th style="text-transform: uppercase; font-size: 14px; text-align: left; "> <br>
                            <span style="font-size: 12px; font-weight: 300;"> </span>
                        </th>
                        <td colspan="2"></td>
                        <th style="text-transform: uppercase; font-size: 14px; text-align: left; ">Property Address:
                            <br> <span style="font-size: 12px; font-weight: 300;">{{$record->property_address ?? ''}}</span>
                        </th>
                    </tr>
                    <tr>
                        <th style="text-transform: uppercase; font-size: 14px; text-align: left; ">Property Classification: <br>
                            <span style="font-size: 12px; font-weight: 300;">{{$record->class_name ?? ''}}</span>
                        </th>
                        <td colspan="2"></td>
                        <th style="text-transform: uppercase; font-size: 14px; text-align: left; ">KGTIN:
                            <span style="font-size: 12px; font-weight: 300;">{{$record->owner_kgtin ?? ''}}</span>
                        </th>
                    </tr>
                    <tr>
                        <th style="text-transform: uppercase; font-size: 14px; text-align: left; "> <br>
                            <span style="font-size: 12px; font-weight: 300;"></span>
                        </th>
                        <td colspan="2"></td>
                        <th style="text-transform: uppercase; font-size: 14px; text-align: left; ">PHONE:
                            <span style="font-size: 12px; font-weight: 300;">{{$record->owner_gsm ?? ''}}</span>
                        </th>
                    </tr>
                </table>
            </div>

            <table class=" mt-1" style="width: 100%; border-collapse: collapse;
            border-right: 1px solid #000000; border-left: 1px solid #000000; border-bottom: 1px solid #000000;">
                <tr>
                    <td class="table-trr" colspan="3" style="font-size: 14px; font-weight: 500; text-transform: uppercase; text-align: center;">
                        {{$record->year}} LAND USE CHARGE(LUC) OF PROPERTY
                    </td>
                </tr>
                <tr>
                    <th class="table-trr" style="text-transform: uppercase; font-size: 14px; text-align: center; ">Assessed Value (NGN)</th>
                    <th class="table-trr" style="text-transform: uppercase; font-size: 14px; text-align: center; ">Charge Rate (%)</th>
                    <th class="table-trr" style="text-transform: uppercase; font-size: 14px; text-align: center; ">LUC Amount (NGN)</th>
                </tr>
                <tr>
                    <td class="table-trr text-center">{{number_format($record->assessed_value ?? 0)}}</td>
                    <td class="table-trr text-center">{{$record->cr ?? 0 }}</td>
                    <td class="table-trr text-center">{{number_format($record->bill_amount ?? 0) }}</td>
                </tr>
                <tr>
                    <th colspan="2"  style="text-transform: uppercase; border-right: 1px solid #000000; font-size: 14px; text-align: left; margin-left: 30px;">Annual Land Use Charge
                        {{$record->year ?? ''}}</th>
                    <td style="text-align: center;">{{ number_format($record->bill_amount ?? 0)  }}</td>
                </tr>
                <tr>
                    <th  colspan="2" style="text-transform: uppercase; border-right: 1px solid #000000; font-size: 14px; text-align: left; margin-left: 30px;">Total Amount Payable</th>
                    <td style="text-align: center; ">{{  number_format($record->bill_amount)  }}</td>
                </tr>
                <tr>
                    <th colspan="2" style="text-transform: uppercase; border-right: 1px solid #000000; font-size: 10px; text-align: left; ">
                        Revenue Code: 12020908
                    </th>
                    <td class=""></td>
                </tr>
            </table>

            <h5 class="mt-3" style="font-size: 14px;">Amount in Words: <span style="text-transform: capitalize;">{{ numberToWords($record->bill_amount ?? 0 )}}</span> Only</h5>
            <table style="width: 100%; border-collapse: collapse; ">
                <tr>
                    <td style="width: 50%; vertical-align: top; padding: 10px; ">
                        <h3 class="text-uppercase" style="font-size: 16px;">How to Pay</h3>
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td><strong>Bank:</strong></td>
                                <td>All bank branches across the State.</td>
                            </tr>
                            <tr>
                                <td><strong>POS:</strong></td>
                                <td>Kogi State Revenue POS Agent.</td>
                            </tr>
                            <tr>
                                <td><strong>Web:</strong></td>
                                <td>
                                    Visit <a href="https://kgirs.kslas.ng">https://kgirs.kslas.ng</a> for payment, verification, and objection services.
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td style="width: 50%; vertical-align: top; padding: 5px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="border: 1px solid #000; padding: 1px; text-align: center;">1</td>
                                <td style="border: 1px solid #000; padding: 1px;">Payment within 5 days of receipt of Demand Notice</td>
                                <td style="border: 1px solid #000; padding: 1px; text-align: center;">15% discount</td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #000; padding: 1px; text-align: center;">2</td>
                                <td style="border: 1px solid #000; padding: 1px;">Payment within 15 days of receipt of Demand Notice</td>
                                <td style="border: 1px solid #000; padding: 1px; text-align: center;">10% discount</td>
                            </tr>
                            <tr>
                                <td style="border: 1px solid #000; padding: 1px; text-align: center;">3</td>
                                <td style="border: 1px solid #000; padding: 1px;">Payment within 25 days of receipt of Demand Notice</td>
                                <td style="border: 1px solid #000; padding: 1px; text-align: center;">5% discount</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="width: 33%; text-align: center; vertical-align: top; padding: 10px; ">
                        <img src="{{asset('assets/images/signature.png')}}" height="82" width="82" alt="Signature">
                        <h5 style="font-size: 16px; margin: 5px 0;">Sule Salihu Enehe</h5>
                        <p style="margin: 0;">Executive Chairman</p>
                    </td>
                    <td style="width: 17%; text-align: center; vertical-align: middle; padding: 10px; ">
                        <img src="{{asset('assets/images/scan-me.png')}}" height="130" width="100" alt="Scan">
                    </td>
                    <td style="width: 50%; vertical-align: top; padding: 10px;">
                        <p style="font-size: 12px; margin: 0;">
                            Note that you have the right of objection within the thirty (30) days notice period, after which the Demand Notice becomes final and conclusive.
                        </p>
                        <p style="font-size: 12px; margin-top: 10px;">
                            However, for the objection to be valid, you are expected to forward payment evidence of your undisputed position alongside your objection.
                            Objection can be done via <a href="#">https://kgirs-billing.kslas.ng</a>.
                        </p>
                    </td>
                </tr>
            </table>
            <div class="col-md-12 col-sm-12" >
                <img style="width: inherit; height: 100px;" src="{{ asset('assets/images/cutout.png')  }}" alt="">
            </div>
        </div>


    @endforeach
</body>
</html>
