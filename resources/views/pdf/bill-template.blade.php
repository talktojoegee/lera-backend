<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
<style>


    .bill-heading-two{
        color: #123319 !important;
        font-size: 28px;
        font-weight: bolder;
        letter-spacing:5px;
    }
    .bill-heading-three{
        color: #3870C2 !important;
        /*font-size:16px;*/
        font-size:24px;
        font-weight: bold;
        letter-spacing: 1px;
    }
    .bill-heading-four{
        color: #181518 !important;
        /*font-size:14px;*/
        font-size:24px;
        font-weight: bold;
    }
    .bill-heading-five{
        color: #151415 !important;
        /*font-size:14px;*/
        font-size:24px;
        font-weight: bold;
    }


    .bill-wrapper{
        width:100%;
        min-height: 200px;
        max-height: auto;
        border: 1px solid #6F6E6F;
        border-radius: 30px;
        margin-top: 10px;
    }
    .owner-details{
        /*font-size: 16px;*/
        font-size: 32px !important;
        font-weight: bold;
        text-align:center;
        color: #030202;
        margin-top: 5px;
        border-bottom: 1px solid #030202;
    }
    .owner-labels{
        /*font-size: 14px;*/
        font-size: 28px !important;
        font-weight: bold;
    }
    .label-value{
        color: #1a191a !important;
        /*font-size:14px;*/
        font-size:28px;
    }
    .watermark{
        width: 100%;
        height: auto;
        display: block;
        background-repeat: no-repeat;
        background-size: cover;
        opacity:0.8
    }

    .watermark-container {
        position: relative;
        overflow: hidden;
    }
    .general{
        font-size: 24px !important;
    }



    th{
        font-size: 10px;
        text-transform: uppercase;
        padding: 7px;
    }
    td{
        font-size:14px;
    }

    .red-highlight{
        background: #FBCCCC;
    }
    .green-highlight{
        background: #93F393;
    }


    .emphasis{
        font-weight: 900;
    }
    .table-tr{
        color: #030202;
        border-bottom: 1px solid #000000;
        padding:30px;


    }
    .td-content{
        font-weight: bold;
        padding: 20px;
        border-right: 2px solid #000000;

    }



    .a4-size {
        width: 210mm;
        height: 297mm;
        margin: auto;
        padding: 20mm;
        box-shadow: 0px 0px 10px rgba(0, 0, 0, 0.1);
    }


    @media print {
        body {
            margin: 0;
            padding: 0;
        }
        .a4-size {
            width: 210mm;
            height: 297mm;
            box-shadow: none;
            margin: 0;
            padding: 0;
            page-break-after: always;
        }
    }

    .emphasis{
        font-weight: 900;
    }
    .table-tr{
        color: #030202;
        border-bottom: 1px solid #000000;

    }
    .td-content{
        font-weight: bold;
        padding: 2px;
        border-right: 2px solid #000000;
    }

</style>
<div style="display:block; overflow: hidden;" class="row .a4-size printSection" id="content-to-pdf" >
    <div class="col-sm-12  col-md-12 " >
        <div class="card pl-0 pr-0 pb-0"  >
            <div class="card-body p-0 m-0">
                <div class="pl-0 pr-0 pb-0 " style="background-image: url({{ asset('assets/images/kogi-watermark.png') }});  background-repeat: no-repeat;
  background-size: cover;
  background-position: center;" >
                    <div class="container">
                        <div class="row text-center mt-5">
                            <div class="col-md-2 ">

                            </div>
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-md-1 d-flex justify-content-end">
                                        <img src="{{asset('assets/images/kgirs.png')}}" height="82" width="82" alt="KGIRS">
                                    </div>
                                    <div class="col-md-10">

                                        <h2 class="bill-heading-two">
                                            KOGI STATE GOVERNMENT
                                        </h2>

                                        <h3 class="bill-heading-three">KOGI STATE INTERNAL REVENUE SERVICE</h3>
                                        <h4 class="bill-heading-four">NO. 1 BEACH ROAD, LOKOJA, KOGI STATE, NIGERIA.</h4>
                                        <h5 class="bill-heading-five">PHONE: 08083427276</h5>
                                        <div class="row">
                                            <div class="col-md-12 d-flex justify-content-center mt-1">
                                                <div class="table-responsive w-100">
                                                    <table class="w-100">
                                                        <tr style="background:#0a3622; color: #ffffff;">
                                                            <td style="padding: 2px; font-weight:bold; font-size:19px;">LAND USE CHARGE ASSESSMENT item?.assessmentYear}}</td>
                                                        </tr>
                                                        <tr style="background:#3870C2; color: #ffffff;">
                                                            <td style="padding: 2px; font-weight:bold;font-size:20px; ">DEMAND NOTICE</td>
                                                        </tr>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-1 d-flex justify-content-start">
                                        <img src="{{asset('assets/images/log.png')}}" height="82" width="160" alt="KGIRS">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row center">
                            <div class="col-md-9 col-offset-1 offset-md-1  " style="font-size:19px;">
                                <p style="font-size:19px;">
                                    In line with the provisions of Kogi State Land Use Charge Law 2024 and other matters connected therewith,
                                    we hereby present to you/your organization due obligation to the State in respect of the Revenue stated below.
                                </p>
                            </div>
                        </div>

                        <div class="row watermar p-5" style="margin-top: -40px;" >
                            <div class="col-md-12 col-sm-12">
                                <div class="row bill-wrapper">
                                    <h3 class="text-uppercase h3-fs text-center owner-details">Property/Owner Details:</h3>

                                    <div class="container">
                                        <div class="row">
                                            <div class="mb-1 col-md-6 ">
                                                <h3 class="text-uppercase owner-labels">Property ID:</h3>
                                                <h5 class="text-uppercase label-value">item?.buildingCode || '-'}}</h5>
                                            </div>
                                            <div class="mb-1 col-md-6 align-content-end">
                                                <h3 class="text-uppercase owner-labels">Date:</h3>
                                                <h5 class="text-uppercase label-value">item?.entryDate}}</h5>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="mb-1 col-md-6 ">
                                                <h3 class="text-uppercase owner-labels">Bill To:</h3>
                                                <h5 class="text-uppercase label-value">item?.propertyName || 'Property Owner'}}
                                                    <br> item?.propertyAddress || '-'}},
                                                    item?.contactAddress || ''}}
                                                </h5>
                                            </div>
                                            <div class="mb-1 col-md-6 align-content-end">
                                                <h3 class="text-uppercase owner-labels">Assessment No.:</h3>
                                                <h5 class="text-uppercase label-value">item?.assessmentNo || '-'}}</h5>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="mb-1 col-md-6 ">
                                                <h3 class="text-uppercase owner-labels">Property Classification:</h3>
                                                <h5 class="text-uppercase label-value">item?.propertyClass || '-'}}</h5>
                                            </div>
                                            <div class="mb-1 col-md-6 align-content-end">
                                                <h3 class="text-uppercase owner-labels">Property Address:</h3>
                                                <h5 class="text-uppercase label-value">item?.propertyAddress || '-'}},
                                                    item?.contactAddress || ''}} </h5>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="mb-1 col-md-6 ">

                                            </div>
                                            <div class="mb-1 col-md-6 align-content-end">
                                                <h3 class="text-uppercase owner-labels">KGTIN:</h3>
                                                <h5 class="text-uppercase label-value">item?.kgTin || '-'}}</h5>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="col-md-6"></div>
                                            <div class="mb-1 col-md-6 ">
                                                <h3 class="text-uppercase owner-labels">PHONE:</h3>
                                                <h5 class="text-uppercase label-value">item?.phoneNo || '-'}}</h5>
                                            </div>
                                        </div>
                                        <div class="row">
                                            <div class="row">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12 mt-3  mt-4">
                                <div class="table-responsive text-center w-100">
                                    <table class="w-100 general" style="border: 1px solid #000000;">
                                        <tr class="table-tr">
                                            <td colspan="3" style="padding: 2px;" ><h3 class="text-uppercase h3-fs text-center owner-details">item?.assessmentYear}} LAND USE CHARGE (LUC) OF PROPERTY</h3> </td>
                                        </tr>
                                        <tr class="table-tr">
                                            <td class="td-content">ASSESSED VALUE ( APP_CURRENCY }})</td>
                                            <td style="font-weight: bold; padding: 2px; border-right: 2px solid #000000;">CHARGE RATE (%)</td>
                                            <td style="font-weight: bold; padding: 2px;"> LUC AMOUNT ( APP_CURRENCY }})</td>
                                        </tr>
                                        <tr class="table-tr">
                                            <td style="font-weight: bold; padding: 2px;">item?.assessValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}}  </td>
                                            <td style="font-weight: bold; padding: 2px;"> item?.chargeRate}}  </td>
                                            <td style="font-weight: bold; padding: 2px;">  (item?.billAmount).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</td>
                                        </tr>
                                        <tr class="table-tr">
                                            <td class="td-content">LAND USE CHARGE item?.assessmentYear}} :</td>
                                            <td style="font-weight: bold; padding: 2px;">(item?.billAmount).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</td>
                                            <td></td>
                                        </tr>
                                        <tr class="table-tr ">
                                            <td class="td-content text-uppercase " style="border-top:none !important;">Total Amount Payable :</td>
                                            <td style="font-weight: bold; padding: 2px;">(item?.billAmount + item?.bbf).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</td>
                                            <td></td>
                                        </tr>
                                    </table>
                                </div>
                                <h6 class="inline-block emphasis mt-2 general">AMOUNT IN WORDS: </h6> <span class="general" style="text-transform: capitalize;"> &nbsp; &nbsp; returnAmountInWords(item?.billAmount)}} only</span>
                            </div>

                            <div class="col-md-12 col-sm-12 mt-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="text-uppercase"><strong class="emphasis general">How to Pay</strong></p>
                                        <ul class="general">
                                            <li> <strong class="emphasis ">BANK:</strong> All bank branches across the State.</li>
                                            <li> <strong class="emphasis">POS:</strong> Kogi State Revenue POS Agent.</li>
                                            <li> <strong class="emphasis">WEB:</strong> Visit <a href="#">https://kgirs.kslas.ng</a> for payment, verification and objection services</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="table-responsive">
                                            <table class="w-100 general"  style="border:1px solid #000000; padding:3px;">
                                                <tr class="table-tr general">
                                                    <td style="padding:3px; border:1px solid #000000;">1</td>
                                                    <td style="text-wrap:normal; width:250px;">Payment within 5 days of receipt of Demand Notice</td>
                                                    <td style="padding:3px; border:1px solid #000000;">15% Discount</td>
                                                </tr>
                                                <tr class="table-tr general">
                                                    <td style="padding:3px; border:1px solid #000000;">2</td>
                                                    <td style="text-wrap:normal; width:250px;">Payment within 15 days of receipt of Demand Notice</td>
                                                    <td style="padding:3px; border:1px solid #000000;">10% Discount</td>
                                                </tr>
                                                <tr class="table-tr general">
                                                    <td style="padding:3px; border:1px solid #000000;">3</td>
                                                    <td style="text-wrap:normal; width:250px;">Payment within 25 days of receipt of Demand Notice</td>
                                                    <td style="padding:3px; border:1px solid #000000;">5% Discount</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-4 general">
                                        <img src="{{asset('assets/images/signature.png')}}" height="82" width="82" alt="Signature">
                                        <h5>Sule Salihu Enehe</h5>
                                        <p>Executive Chairman</p>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="row">
                                            <div class="col-md-12">
                                                <h6 class=""><strong class="emphasis general">REVENUE CODE</strong> </h6>
                                                <h6 class="emphasis"><strong class="emphasis general">12020908</strong></h6>
                                                <img src="{{asset('assets/images/scan-me.png')}}" height="130" width="100" alt="Scan">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="mt-2">NOTE:</h6>
                                        <p class="general">Note that you the right of objection within the thirty(30) days notice period, after which the Demand Notice becomes final and conclusive.
                                        </p>
                                        <p class="general">However, for the objection to be valid, you are expected to forward payment evidence of your undisputed position alongside your objection.
                                            Objection can be done via <a href="#">https://kgirs-billing.kslas.ng</a>
                                        </p>
                                        <br>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="col-md-12 col-sm-12 mt-4" style="background: url({{asset('assets/images/cutout.png')}}); height: 210px; width: 100%; background-size: cover;
        background-position: center;  background-repeat: no-repeat;">

                </div>
            </div>
        </div>
    </div>
</div>








