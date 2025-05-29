<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .card {
            margin-bottom: 20px;
        }
        .stats-card {
            text-align: center;
            padding: 20px;
        }
        .stats-value {
            font-size: 2rem;
            font-weight: bold;
        }
        .stats-label {
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4"><?= $title ?></h1>
        
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="stats-value"><?= $statistics['searches'] ?></div>
                    <div class="stats-label">Поисковых запросов</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="stats-value"><?= $statistics['products'] ?></div>
                    <div class="stats-label">Продуктов</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stats-card">
                    <div class="stats-value"><?= $statistics['orders'] ?></div>
                    <div class="stats-label">Заказов</div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                Информация о магазине
            </div>
            <div class="card-body">
                <p><strong>ID приложения:</strong> <?= $appId ?></p>
                <p><strong>URL магазина:</strong> <?= $shopUrl ?></p>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                Недавние действия
            </div>
            <div class="card-body">
                <p>Здесь будет отображаться журнал последних действий.</p>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

