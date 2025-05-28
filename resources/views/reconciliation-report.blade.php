<div class="table-responsive">
    <table class="table table-striped table-bordered">
        <tr class="text-uppercase">
            <th>S/No.</th>
            <th>Date</th>
            <th>Payer Name</th>
            <th>Month</th>
            <th>Year</th>
            <th>Assessment No.</th>
            <th>Building Code</th>
            <th>Status</th>
            <th>Amount</th>
            <th>Reason</th>
        </tr>

        @foreach($report as $index => $record)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ date('d/m/Y', strtotime($record->created_at)) }}</td>
                <td>{{$record->payer_name ?? '' }}</td>
                <td>{{$record->month ?? '' }}</td>
                <td>{{$record->year ?? '' }}</td>
                <td>{{$record->assessment_no ?? '' }}</td>
                <td>{{$record->building_code ?? '' }}</td>
                <td>{{$record->reconciled == 0 ? 'Not Reconciled' : 'Reconciled' }}</td>
                <td>{{ $record->credit ?? 0 }}</td>
                <td>{{ $record->reason }}</td>
            </tr>
        @endforeach
    </table>
</div>

