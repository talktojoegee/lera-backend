<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Land Use Charge — Demand Notice (batch)</title>
    @include('pdf.partials.bill-lera-styles')
</head>
<body>
@foreach($records as $record)
    @include('pdf.partials.bill-lera-page', ['record' => $record])
@endforeach
</body>
</html>
