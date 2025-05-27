<table style="">
    <tr><td class="bold">KGTIN:</td><td style="text-transform: uppercase;">{{$payer_id}}</td></tr>
    <tr><td class="bold">Paid By:</td><td>{{$paid_by}}</td></tr>
    <tr><td class="bold">Address:</td><td>{{$address}}</td></tr>
    <tr>
        <td class="bold" style="vertical-align: top;">Amount:</td>
        <td style="word-break: break-word; white-space: normal;">
            {{$amount}} <br>
            ({{$amount_words}} Only)
        </td>
    </tr>
    <tr><td class="bold">Being:</td><td>{{$payment_purpose}}</td></tr>
    <tr>
        <td class="bold">Paid At:</td>
        <td style="word-wrap: break-word;">{{$pay_mode}}({{$bankName}}) // Receipt No: {{$receipt_no}}
            <br>  // Invoice No: <span style="text-transform: uppercase;">{{$assessmentNo}}</span>
            <br>Swift Code: <span style="text-transform: uppercase;">{{$swift_code}}</span>// Paid Date:
            {{$payment_date}}</td>
    </tr>
    <tr><td class="bold">Agency:</td><td><span style="text-transform: uppercase;">Bureau for Lands and Urban Development</span></td></tr>
    <tr><td class="bold">Tax Station:</td><td style="text-transform: uppercase;">{{$tax_station}}</td></tr>
</table>
