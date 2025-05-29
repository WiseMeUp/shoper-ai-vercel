<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ошибка</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .error-container {
            text-align: center;
            margin-top: 100px;
        }
        .error-icon {
            font-size: 5rem;
            color: #dc3545;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-container">
            <div class="error-icon">⚠️</div>
            <h1 class="mb-4">Произошла ошибка</h1>
            <div class="alert alert-danger">
                <?= $message ?? 'Неизвестная ошибка' ?>
            </div>
            <a href="/" class="btn btn-primary">Вернуться на главную</a>
        </div>
    </div>
</body>
</html>

