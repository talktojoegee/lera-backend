<table>
    <thead>
    <tr>
        <th>S/N</th>
        <th>Sector</th>
        <th>Users</th>
        <th>Review</th>
        <th>Verification</th>
        <th>Authorization</th>
        <th>Approval</th>
    </tr>
    </thead>
    <tbody>
    @foreach($reportData as $index => $sector)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $sector['sector'] }}</td>
            <td>
                @foreach($sector['users'] as $user)
                    {{ $user['name'] }} ({{ $user['email'] }})<br>
                @endforeach
            </td>
            <td>{{ $sector['review'] }}</td>
            <td>{{ $sector['verification'] }}</td>
            <td>{{ $sector['authorization'] }}</td>
            <td>{{ $sector['approval'] }}</td>

        </tr>
    @endforeach
    </tbody>
</table>
