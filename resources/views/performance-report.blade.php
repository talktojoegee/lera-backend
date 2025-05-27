<table>
    <tr>
        <td colspan="6">
            Showing Performance report from {{ date('d/m/Y', strtotime($from)) }} to {{ date('d/m/Y', strtotime($to)) }}
        </td>
    </tr>
    <thead>
    <tr>
        <th>S/N</th>
        <th>Name</th>
        <th>Reviewed</th>
        <th>Verified</th>
        <th>Authorized</th>
        <th>Approved</th>
    </tr>
    </thead>
    <tbody>
    @foreach($reportData as $index => $record)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>
                {{ $record['user']['name'] }} ({{ $record['user']['email'] }})
            </td>
            <td>{{ $record['reviewed'] }}</td>
            <td>{{ $record['verified'] }}</td>
            <td>{{ $record['authorized'] }}</td>
            <td>{{ $record['approved'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
