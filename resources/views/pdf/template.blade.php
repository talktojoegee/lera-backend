<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Document</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ddd;
        }
        h1 {
            text-align: center;
            font-size: 18px;
        }
        p {
            text-align: justify;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>Generated PDF</h1>
    <p>This is a sample PDF generated using Laravel and DomPDF.</p>
    <p>Data passed: {{ $data['example'] ?? 'No Data Provided' }}</p>
</div>
</body>
</html>
