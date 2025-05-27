<div class="table-responsive">
    <table class="table table-striped table-bordered">
        <tr>
            <td colspan="8">
                Rate:   KGIRS: {{$kgirs}}% - (LGA:{{$lga}}%), Newwaves: {{$newwaves}}%
            </td>
        </tr>
        <tr>
            <td colspan="8">Showing settlement report between {{date('d/m/Y', strtotime($from))}} to {{date('d/m/Y', strtotime($to))}}</td>
        </tr>
        <tr class="text-uppercase">
            <th>S/No.</th>
            <th>Date</th>
            <th>Building Code</th>
            <th>Assessment No.</th>
            <th class="text-right">Amount</th>
            <th class="text-right">KGIRS({{ $kgirs }}%)</th>
            @foreach($lgaList as $item)
                <th class="green-highlight">{{ $item->lga_name }}</th>
            @endforeach
            <th class="text-right">Newwaves({{ $newwaves }}%)</th>
        </tr>

        @php
            $totalAmount = 0;
            $totalKgirs = 0;
            $totalNewwaves = 0;
            $lgaTotals = [];
        @endphp

        @foreach($report as $index => $payment)
            @php
                $amount = $payment->amount ?? 0;
                $kgirsVal =( ($kgirs * 0.01) * $amount ) - ( ( ($kgirs * 0.01) * $amount ) * ($lga * 0.01) );
                $lgaVal = ( ( $amount * ($kgirs /100)) * ($lga /100));
                $newwavesVal =  ($newwaves * 0.01) * $amount;

                $totalAmount += $amount;
                $totalKgirs += $kgirsVal;
                $totalNewwaves += $newwavesVal;

                $lgaName = $payment->lgaName;
                if (!isset($lgaTotals[$lgaName])) {
                    $lgaTotals[$lgaName] = 0;
                }
                $lgaTotals[$lgaName] += $lgaVal;
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $payment->date }}</td>
                <td style="text-transform: uppercase;">{{ $payment->buildingCode }}</td>
                <td style="text-transform: uppercase;">{{ $payment->assessmentNo }}</td>
                <td style="text-align: right;">{{ number_format($amount, 2) }}</td>
                <td style="text-align: right;">{{ number_format($kgirsVal, 2) }}</td>

                @foreach($lgaList as $item)
                    <td style="text-align: right;">
                        @if($payment->lgaName === $item->lga_name)
                            {{ number_format($lgaVal, 2) }}
                        @else
                            0
                        @endif
                    </td>
                @endforeach

                <td style="text-align: right;">{{ number_format($newwavesVal, 2) }}</td>
            </tr>
        @endforeach

        <tr class="font-weight-bold bg-light">
            <td colspan="4" class="text-end">TOTAL</td>
            <td class="text-right">{{ number_format($totalAmount, 2) }}</td>
            <td class="text-right">{{ number_format($totalKgirs, 2) }}</td>
            @foreach($lgaList as $item)
                <td class="text-right">{{ number_format($lgaTotals[$item->lga_name] ?? 0, 2) }}</td>
            @endforeach
            <td class="text-right">{{ number_format($totalNewwaves, 2) }}</td>
        </tr>
    </table>
</div>

