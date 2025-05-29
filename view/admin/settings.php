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
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4"><?= $title ?></h1>
        
        <div class="card">
            <div class="card-header">
                Настройки поиска
            </div>
            <div class="card-body">
                <form id="settingsForm">
                    <div class="form-group">
                        <label for="enableAiSearch">Включить AI Search</label>
                        <div class="custom-control custom-switch">
                            <input type="checkbox" class="custom-control-input" id="enableAiSearch" 
                                <?= $settings['enableAiSearch'] ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="enableAiSearch"></label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="searchRelevance">Релевантность поиска (0.1 - 1.0)</label>
                        <input type="range" class="custom-range" id="searchRelevance" min="0.1" max="1" step="0.1" 
                            value="<?= $settings['searchRelevance'] ?>">
                        <small class="form-text text-muted">
                            Текущее значение: <span id="relevanceValue"><?= $settings['searchRelevance'] ?></span>
                        </small>
                    </div>
                    
                    <div class="form-group">
                        <label for="maxResults">Максимальное количество результатов</label>
                        <input type="number" class="form-control" id="maxResults" 
                            value="<?= $settings['maxResults'] ?>" min="1" max="100">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Сохранить настройки</button>
                </form>
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
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            // Обновление отображаемого значения при изменении ползунка
            $('#searchRelevance').on('input', function() {
                $('#relevanceValue').text($(this).val());
            });
            
            // Обработка отправки формы
            $('#settingsForm').on('submit', function(e) {
                e.preventDefault();
                
                const settings = {
                    enableAiSearch: $('#enableAiSearch').is(':checked'),
                    searchRelevance: parseFloat($('#searchRelevance').val()),
                    maxResults: parseInt($('#maxResults').val())
                };
                
                // AJAX запрос для сохранения настроек
                // В реальном приложении здесь должен быть код для отправки настроек на сервер
                console.log('Отправка настроек:', settings);
                
                alert('Настройки сохранены!');
            });
        });
    </script>
</body>
</html>

