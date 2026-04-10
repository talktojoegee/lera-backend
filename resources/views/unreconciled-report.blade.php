<div class="table-responsive">
    <table class="table table-striped">
        <thead>
        <tr>
            <th scope="col">#</th>
            <th scope="col" class="red-highlight">Value Date(Bank)</th>
            <th class="red-highlight">Details(Bank)</th>
            <th class="red-highlight">Assessment No.(Bank)</th>
            <th scope="col" class="red-highlight" style="text-align: right;">Amount(Bank)</th>

            <th class="green-highlight">Building Code(Billing)</th>
            <th class="green-highlight">Assessment No.(Billing)</th>
            <th class="green-highlight" scope="col" style="text-align: right;">Amount(Billing)</th>
            <th>Status</th>
            <th scope="col">Reason</th>
        </tr>
        </thead>
        <tbody>
        @foreach($report as $index => $record)
            <tr>
                <th scope="row">{{ $index+1 }}</th>
                <td class="red-highlight">{{ date('d/m/Y', strtotime($record->value_date)) }}</td>
                <td class="red-highlight">{{ $record->details ?? '' }}</td>
                <td class="red-highlight">{{ $record->assessment_no ?? '' }}</td>
                <td style="text-align: right;" class="red-highlight">
                    @if($record->dc_type == 2)
                        <span class="text-primary">{{ $record->bank_amount }}</span>
                    @else
                        <span class="text-primary">{{ $record->bank_amount }}</span>
                    @endif
                </td>

                <td  class="green-highlight">
                    @if($record->dc_type == 2)
                        <span class="text-primary">{{ $record->bill_payment_building_code }}</span>
                    @endif
                </td>
                <td class="green-highlight">
                    @if($record->dc_type == 2)
                        {{$record->bill_payment_assessment_no}}
                    @endif
                </td>
                <td class="green-highlight">
                    @if($record->dc_type == 2)
                        {{$record->billing_amount }}
                    @endif
                </td>
                <td>
                    @if($record->matched == 0)
                        <span  class="badge bg-danger">Not matched</span>
                    @else

                    @endif
                </td>
                @if($record->matched == 0)
                    <td>
                        @if($record->dc_type == 2)
                            <span  class="badge bg-danger">Not matched</span>
                        @endif
                    </td>
                @endif
                @if($record->matched == 1)
                    <td>
                        @if($record->dc_type == 2)
                            <span  class="badge bg-danger">Matched</span>
                        @endif
                    </td>
                @endif
                <td>
                    @if($record->dc_type == 2)
                        {{$record->reason ?? ''}}
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

