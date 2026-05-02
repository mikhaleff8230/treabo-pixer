<?php
// Отключаем вывод ошибок в HTML
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Загружаем переменные окружения из .env файла
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!empty($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// Функция для отправки JSON-ответа
function sendJsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Функция для обработки ошибок
function handleError($message, $statusCode = 400) {
    sendJsonResponse([
        'success' => false,
        'message' => $message
    ], $statusCode);
}

// Проверка окружения
if (getenv('APP_ENV') !== 'production') {
    handleError("Этот скрипт предназначен только для боевого сервера!", 403);
}

// Проверка наличия необходимых переменных окружения
$requiredEnvVars = [
    'TINKOFF_TERMINAL_KEY',
    'TINKOFF_PASSWORD',
    'TINKOFF_API_URL'
];

$missingVars = [];
foreach ($requiredEnvVars as $var) {
    if (empty(getenv($var))) {
        $missingVars[] = $var;
    }
}

if (!empty($missingVars)) {
    handleError("Отсутствуют переменные окружения: " . implode(', ', $missingVars), 500);
}

// Функция для создания подписи
function generateToken($data, $password) {
    $values = array_filter($data, function($value) {
        return $value !== null && $value !== '';
    });
    ksort($values);
    $token = implode('', $values) . $password;
    return hash('sha256', $token);
}

// Функция для логирования
function logDebug($message, $data = null) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log .= "\nData: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    error_log($log . "\n", 3, __DIR__ . '/tinkoff-debug.log');
}

// Функция для создания платежа
function createPayment($amount) {
    $terminalKey = getenv('TINKOFF_TERMINAL_KEY');
    $password = getenv('TINKOFF_PASSWORD');
    $apiUrl = getenv('TINKOFF_API_URL');
    
    logDebug("Начало создания платежа", [
        'amount' => $amount,
        'terminalKey' => $terminalKey,
        'apiUrl' => $apiUrl
    ]);
    
    $data = [
        'TerminalKey' => $terminalKey,
        'Amount' => $amount * 100, // Конвертируем в копейки
        'OrderId' => 'TEST-' . time(),
        'Description' => 'Тестовый платеж',
        'SuccessURL' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment/success',
        'FailURL' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment/fail',
        'DATA' => [
            'Email' => 'test@example.com'
        ]
    ];
    
    $data['Token'] = generateToken($data, $password);
    
    logDebug("Отправка запроса в API", $data);
    
    $ch = curl_init($apiUrl . '/Init');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Отключаем проверку SSL для отладки
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Отключаем проверку SSL для отладки
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    logDebug("Ответ от API", [
        'httpCode' => $httpCode,
        'response' => $response,
        'curlError' => $curlError
    ]);
    
    if ($curlError) {
        throw new Exception("Ошибка CURL: " . $curlError);
    }
    
    if ($httpCode !== 200) {
        throw new Exception("Ошибка API: HTTP код {$httpCode}");
    }
    
    $result = json_decode($response, true);
    if (!$result) {
        throw new Exception("Ошибка декодирования JSON: " . json_last_error_msg());
    }
    
    if (isset($result['ErrorCode'])) {
        throw new Exception("Ошибка API: " . ($result['Message'] ?? 'Неизвестная ошибка') . 
                          " (Код: " . $result['ErrorCode'] . ")");
    }
    
    logDebug("Платеж успешно создан", $result);
    
    return $result;
}

// Функция для проверки статуса платежа
function checkPaymentStatus($paymentId) {
    $terminalKey = getenv('TINKOFF_TERMINAL_KEY');
    $password = getenv('TINKOFF_PASSWORD');
    $apiUrl = getenv('TINKOFF_API_URL');
    
    logDebug("Начало проверки статуса платежа", [
        'paymentId' => $paymentId,
        'terminalKey' => $terminalKey,
        'apiUrl' => $apiUrl
    ]);
    
    $data = [
        'TerminalKey' => $terminalKey,
        'PaymentId' => $paymentId
    ];
    
    $data['Token'] = generateToken($data, $password);
    
    logDebug("Отправка запроса в API", $data);
    
    $ch = curl_init($apiUrl . '/GetState');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Отключаем проверку SSL для отладки
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Отключаем проверку SSL для отладки
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    logDebug("Ответ от API", [
        'httpCode' => $httpCode,
        'response' => $response,
        'curlError' => $curlError
    ]);
    
    if ($curlError) {
        throw new Exception("Ошибка CURL: " . $curlError);
    }
    
    if ($httpCode !== 200) {
        throw new Exception("Ошибка API: HTTP код {$httpCode}");
    }
    
    $result = json_decode($response, true);
    if (!$result) {
        throw new Exception("Ошибка декодирования JSON: " . json_last_error_msg());
    }
    
    if (isset($result['ErrorCode'])) {
        throw new Exception("Ошибка API: " . ($result['Message'] ?? 'Неизвестная ошибка') . 
                          " (Код: " . $result['ErrorCode'] . ")");
    }
    
    logDebug("Статус платежа получен", $result);
    
    return $result;
}

// Обработка API запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            handleError("Неверный формат JSON");
        }
        
        $amount = $data['amount'] ?? 1.00;
        $payment = createPayment($amount);
        
        sendJsonResponse([
            'success' => true,
            'payment_id' => $payment['PaymentId'] ?? null,
            'payment_url' => $payment['PaymentURL'] ?? null
        ]);
    } catch (Exception $e) {
        handleError($e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['payment_id'])) {
    try {
        $status = checkPaymentStatus($_GET['payment_id']);
        
        sendJsonResponse([
            'success' => true,
            'status' => $status['Status'] ?? 'Неизвестен'
        ]);
    } catch (Exception $e) {
        handleError($e->getMessage());
    }
}

// Если это не API запрос, показываем HTML страницу
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Отладка Тинькофф</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .log-container {
            background: #1e1e1e;
            color: #fff;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            max-height: 500px;
            overflow-y: auto;
        }
        .log-entry {
            margin-bottom: 5px;
            border-bottom: 1px solid #333;
            padding-bottom: 5px;
        }
        .log-time {
            color: #888;
        }
        .log-error {
            color: #ff6b6b;
        }
        .log-success {
            color: #51cf66;
        }
        .log-info {
            color: #339af0;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <h1 class="mb-4">Отладка Тинькофф</h1>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Создать тестовый платеж</h5>
                    </div>
                    <div class="card-body">
                        <form id="paymentForm">
                            <div class="mb-3">
                                <label class="form-label">Сумма (руб.)</label>
                                <input type="number" class="form-control" name="amount" value="1.00" step="0.01" min="0.01">
                            </div>
                            <button type="submit" class="btn btn-primary">Создать платеж</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Проверить статус</h5>
                    </div>
                    <div class="card-body">
                        <form id="statusForm">
                            <div class="mb-3">
                                <label class="form-label">ID платежа</label>
                                <input type="text" class="form-control" name="payment_id" required>
                            </div>
                            <button type="submit" class="btn btn-info">Проверить</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Лог операций</h5>
                <button class="btn btn-sm btn-outline-secondary" onclick="clearLog()">Очистить</button>
            </div>
            <div class="card-body">
                <div id="logContainer" class="log-container"></div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function addLogEntry(message, type = 'info') {
            const container = document.getElementById('logContainer');
            const entry = document.createElement('div');
            entry.className = `log-entry log-${type}`;
            
            const time = new Date().toLocaleTimeString();
            entry.innerHTML = `<span class="log-time">[${time}]</span> ${message}`;
            
            container.appendChild(entry);
            container.scrollTop = container.scrollHeight;
        }

        function clearLog() {
            document.getElementById('logContainer').innerHTML = '';
        }

        document.getElementById('paymentForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const amount = e.target.amount.value;
            
            try {
                addLogEntry(`Создание платежа на сумму ${amount} руб.`, 'info');
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ amount })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    addLogEntry(`Платеж создан. ID: ${data.payment_id}`, 'success');
                    addLogEntry(`URL для оплаты: ${data.payment_url}`, 'info');
                } else {
                    addLogEntry(`Ошибка: ${data.message}`, 'error');
                }
            } catch (error) {
                addLogEntry(`Ошибка: ${error.message}`, 'error');
            }
        });

        document.getElementById('statusForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const paymentId = e.target.payment_id.value;
            
            try {
                addLogEntry(`Проверка статуса платежа ${paymentId}`, 'info');
                
                const response = await fetch(`${window.location.href}?payment_id=${paymentId}`);
                const data = await response.json();
                
                if (data.success) {
                    addLogEntry(`Статус платежа: ${data.status}`, 'success');
                } else {
                    addLogEntry(`Ошибка: ${data.message}`, 'error');
                }
            } catch (error) {
                addLogEntry(`Ошибка: ${error.message}`, 'error');
            }
        });
    </script>
</body>
</html> 