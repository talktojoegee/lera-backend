
@php
    $totalPayable = $record->totalPayable ?? (float) ($record->billAmount ?? 0) + (float) ($record->bbf ?? 0);
    $billTo = !empty($record->propertyName)
        ? 'Property Owner c/o ' . $record->propertyName
        : 'Property Owner';
    $entryDate = isset($record->entryDate) ? date('d/m/Y', strtotime($record->entryDate)) : '—';
    $yearLabel = (string) ($record->year ?? date('Y'));
    $amtWords = strtoupper(numberToWords($totalPayable));
    if (! str_ends_with($amtWords, 'NAIRA')) {
        $amtWords .= ' NAIRA';
    }
    $amtWords .= ' ONLY';
@endphp
<div class="lera-page">
    <div class="lera-overlay">
        {{--<div class="lera-year-suffix"> $yearLabel }}</div>--}}

        <table class="lera-kv-table lera-prop-kv" cellspacing="0" cellpadding="0">
            <tr>
                <td class="lera-k">PROPERTY ID:</td>
                <td class="lera-v"><span class="lera-val">{{ $record->buildingCode ?? '' }}</span></td>
            </tr>
            <tr>
                <td class="lera-k">ASSESSMENT NO.:</td>
                <td class="lera-v"><span class="lera-val">{{ strtoupper($record->assessmentNo ?? '') }}</span></td>
            </tr>
            <tr>
                <td class="lera-k">BILL TO:</td>
                <td class="lera-v"><span class="lera-val">{{ $billTo }}</span></td>
            </tr>
            <tr>
                <td class="lera-k">ADDRESS:</td>
                <td class="lera-v"><span class="lera-val">{{ trim(($record->propertyAddress ?? '') . ' ' . ($record->address ?? '')) }}</span></td>
            </tr>
            <tr>
                <td class="lera-k">CATEGORY:</td>
                <td class="lera-v"><span class="lera-val">{{ $record->className ?? '' }}</span></td>
            </tr>
            <tr>
                <td class="lera-k">DATE:</td>
                <td class="lera-v"><span class="lera-val">{{ $entryDate }}</span></td>
            </tr>
        </table>

        @if(!empty($record->qrImage))
            <div class="lera-qr-wrap">
                <table class="lera-qr-inner" width="100%" height="100%" cellspacing="0" cellpadding="0">
                    <tr>
                        <td>
                            <img src="{!! $record->qrImage !!}" alt="" class="lera-qr-img"/>
                        </td>
                    </tr>
                </table>
            </div>
        @endif

        <table class="lera-assess-row" cellspacing="0" cellpadding="0">
            <tr>
                {{--<td class="lera-assess-date"><span class="lera-val"> $entryDate }}</span></td>--}}
                <td class="lera-assess-cat"><span class="lera-val">{{ $record->className ?? '—' }}</span></td>
                <td class="lera-assess-amt"><span class="lera-val">NGN {{ number_format((float) ($record->billAmount ?? 0), 2) }}</span></td>
            </tr>
        </table>

        <div class="lera-total-num"><span class="lera-val">NGN {{ number_format($totalPayable, 2) }}</span></div>
        <div class="lera-total-words"><span class="lera-val">{{ $amtWords }}</span></div>

        <table class="lera-kv-table lera-pod-kv" cellspacing="0" cellpadding="0">
            <tr class="lera-pod-row">
                <td class="lera-k">PROPERTY ID:</td>
                <td class="lera-v"><span class="lera-val">{{ $record->buildingCode ?? '' }}</span></td>
            </tr>
            <tr class="lera-pod-row">
                <td class="lera-k">ASSESSMENT NO.:</td>
                <td class="lera-v"><span class="lera-val">{{ strtoupper($record->assessmentNo ?? '') }}</span></td>
            </tr>
            <tr class="lera-pod-row">
                <td class="lera-k">ADDRESS:</td>
                <td class="lera-v"><span class="lera-val">{{ $record->propertyAddress ?? '' }}</span></td>
            </tr>
            <tr class="lera-pod-row">
                <td class="lera-k">RECEIVED BY (NAME):</td>
                <td class="lera-v"><span class="lera-val">&nbsp;</span></td>
            </tr>
            <tr class="lera-pod-row">
                <td class="lera-k">PHONE NUMBER:</td>
                <td class="lera-v"><span class="lera-val">&nbsp;</span></td>
            </tr>
            <tr class="lera-pod-row">
                <td class="lera-k">DATE RECEIVED:</td>
                <td class="lera-v"><span class="lera-val">&nbsp;</span></td>
            </tr>
            <tr class="lera-pod-row">
                <td class="lera-k">SIGNATURE:</td>
                <td class="lera-v"><span class="lera-val">&nbsp;</span></td>
            </tr>
        </table>
    </div>
</div>
