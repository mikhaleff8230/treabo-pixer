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

// Функция для логирования
function logDebug($message, $data = null) {
    $log = date('Y-m-d H:i:s') . " - " . $message;
    if ($data !== null) {
        $log .= "\nData: " . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    error_log($log . "\n", 3, __DIR__ . '/tinkoff-test.log');
}

// Функция для создания подписи
function generateToken($data, $password) {
    // Удаляем Token из данных перед генерацией
    unset($data['Token']);
    
    // Фильтруем пустые значения
    $values = array_filter($data, function($value) {
        return $value !== null && $value !== '';
    });
    
    // Сортируем по ключам
    ksort($values);
    
    // Формируем строку значений
    $valuesString = '';
    foreach ($values as $key => $value) {
        if (is_array($value)) {
            $valuesString .= json_encode($value, JSON_UNESCAPED_UNICODE);
        } else {
            $valuesString .= $value;
        }
    }
    
    // Добавляем пароль
    $valuesString .= $password;
    
    // Возвращаем SHA-256 хеш
    return hash('sha256', $valuesString);
}

// Функция для проверки переменных окружения
function checkEnvironment() {
    $requiredVars = [
        'TINKOFF_TERMINAL_KEY' => 'Терминальный ключ',
        'TINKOFF_PASSWORD' => 'Пароль',
        'TINKOFF_API_URL' => 'URL API'
    ];
    
    $missingVars = [];
    foreach ($requiredVars as $var => $description) {
        if (empty(getenv($var))) {
            $missingVars[] = "$description ($var)";
        }
    }
    
    if (!empty($missingVars)) {
        throw new Exception("Отсутствуют обязательные переменные окружения: " . implode(', ', $missingVars));
    }
}

// Функция для проверки формата данных
function validatePaymentData($data) {
    $errors = [];
    
    if (!isset($data['TerminalKey']) || empty($data['TerminalKey'])) {
        $errors[] = "Отсутствует TerminalKey";
    }
    
    if (!isset($data['Amount']) || !is_numeric($data['Amount']) || $data['Amount'] <= 0) {
        $errors[] = "Некорректная сумма платежа";
    }
    
    if (!isset($data['OrderId']) || empty($data['OrderId'])) {
        $errors[] = "Отсутствует OrderId";
    }
    
    if (!empty($errors)) {
        throw new Exception("Ошибки в данных платежа: " . implode(', ', $errors));
    }
}

// Функция для создания платежа
function createPayment($amount) {
    try {
        // Проверяем окружение
        checkEnvironment();
        
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
            'Amount' => (int)($amount * 100),
            'OrderId' => 'TEST-' . time(),
            'Description' => 'Тестовый платеж',
            'SuccessURL' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment/success',
            'FailURL' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment/fail',
            'NotificationURL' => 'https://' . $_SERVER['HTTP_HOST'] . '/payment/notification',
            'Receipt' => [
                'Email' => 'test@example.com',
                'Phone' => '+79001234567',
                'Taxation' => 'osn',
                'Items' => [
                    [
                        'Name' => 'Тестовый товар',
                        'Price' => (int)($amount * 100),
                        'Quantity' => 1,
                        'Amount' => (int)($amount * 100),
                        'Tax' => 'vat20',
                        'PaymentMethod' => 'full_payment',
                        'PaymentObject' => 'commodity'
                    ]
                ]
            ]
        ];
        
        // Проверяем данные перед отправкой
        validatePaymentData($data);
        
        $data['Token'] = generateToken($data, $password);
        
        logDebug("Отправка запроса в API", [
            'request_data' => $data,
            'generated_token' => $data['Token'],
            'terminal_key' => $terminalKey,
            'api_url' => $apiUrl
        ]);
        
        $ch = curl_init($apiUrl . '/Init');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        logDebug("Ответ от API", [
            'httpCode' => $httpCode,
            'response' => $response,
            'curlError' => $curlError,
            'request_data' => $data
        ]);
        
        if ($curlError) {
            throw new Exception("Ошибка CURL: " . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Ошибка API: HTTP код {$httpCode}, Ответ: " . $response);
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception("Ошибка декодирования JSON: " . json_last_error_msg() . ", Ответ: " . $response);
        }
        
        // Проверяем успешность операции
        if (!isset($result['Success']) || $result['Success'] !== true) {
            $errorMessage = "Ошибка API: " . ($result['Message'] ?? 'Неизвестная ошибка');
            
            if (isset($result['ErrorCode'])) {
                $errorMessage .= " (Код: " . $result['ErrorCode'] . ")";
                
                // Добавляем специфические сообщения для известных кодов ошибок
                switch ($result['ErrorCode']) {
                    case '1':
                        $errorMessage .= "\nНеверный формат данных";
                        break;
                    case '2':
                        $errorMessage .= "\nНеверный токен";
                        break;
                    case '3':
                        $errorMessage .= "\nНеверный терминальный ключ";
                        break;
                    case '4':
                        $errorMessage .= "\nНеверный пароль";
                        break;
                    case '5':
                        $errorMessage .= "\nНеверная сумма";
                        break;
                    case '6':
                        $errorMessage .= "\nНеверный номер заказа";
                        break;
                    case '7':
                        $errorMessage .= "\nЗаказ уже существует";
                        break;
                    case '8':
                        $errorMessage .= "\nНеверный формат чека";
                        break;
                }
            }
            
            throw new Exception($errorMessage . "\nПолный ответ: " . json_encode($result, JSON_UNESCAPED_UNICODE));
        }
        
        logDebug("Платеж успешно создан", $result);
        
        return $result;
    } catch (Exception $e) {
        logDebug("Ошибка при создании платежа", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

// Функция для проверки статуса платежа
function checkPaymentStatus($paymentId) {
    try {
        checkEnvironment();
        
        $terminalKey = getenv('TINKOFF_TERMINAL_KEY');
        $password = getenv('TINKOFF_PASSWORD');
        $apiUrl = getenv('TINKOFF_API_URL');
        
        $data = [
            'TerminalKey' => $terminalKey,
            'PaymentId' => $paymentId
        ];
        
        $data['Token'] = generateToken($data, $password);
        
        logDebug("Проверка статуса платежа", [
            'payment_id' => $paymentId,
            'request_data' => $data
        ]);
        
        $ch = curl_init($apiUrl . '/GetState');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        logDebug("Ответ от API при проверке статуса", [
            'httpCode' => $httpCode,
            'response' => $response,
            'curlError' => $curlError
        ]);
        
        if ($curlError) {
            throw new Exception("Ошибка CURL: " . $curlError);
        }
        
        if ($httpCode !== 200) {
            throw new Exception("Ошибка API: HTTP код {$httpCode}, Ответ: " . $response);
        }
        
        $result = json_decode($response, true);
        if (!$result) {
            throw new Exception("Ошибка декодирования JSON: " . json_last_error_msg());
        }
        
        if (!isset($result['Success']) || $result['Success'] !== true) {
            throw new Exception("Ошибка API: " . ($result['Message'] ?? 'Неизвестная ошибка'));
        }
        
        return $result;
    } catch (Exception $e) {
        logDebug("Ошибка при проверке статуса", [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        throw $e;
    }
}

// Обработка API запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data) {
            throw new Exception("Неверный формат JSON");
        }
        
        if (isset($data['action']) && $data['action'] === 'check_status') {
            if (!isset($data['payment_id'])) {
                throw new Exception("Отсутствует ID платежа");
            }
            $status = checkPaymentStatus($data['payment_id']);
            echo json_encode([
                'success' => true,
                'status' => $status['Status'] ?? 'UNKNOWN',
                'details' => $status
            ]);
        } else {
            $amount = $data['amount'] ?? 1.00;
            $payment = createPayment($amount);
            
            echo json_encode([
                'success' => true,
                'payment_id' => $payment['PaymentId'] ?? null,
                'payment_url' => $payment['PaymentURL'] ?? null
            ]);
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тест Тинькофф</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-4">
        <h1 class="mb-4">Тест Тинькофф</h1>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
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
                        <div id="paymentResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Проверить статус платежа</h5>
                    </div>
                    <div class="card-body">
                        <form id="statusForm">
                            <div class="mb-3">
                                <label class="form-label">ID платежа</label>
                                <input type="text" class="form-control" name="payment_id" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Проверить статус</button>
                        </form>
                        <div id="statusResult" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('paymentForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const amount = e.target.amount.value;
            const resultDiv = document.getElementById('paymentResult');
            
            try {
                resultDiv.innerHTML = '<div class="alert alert-info">Создание платежа...</div>';
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ amount })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <p>Платеж создан успешно!</p>
                            <p>ID платежа: ${data.payment_id}</p>
                            <p><a href="${data.payment_url}" target="_blank" class="btn btn-primary">Перейти к оплате</a></p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <p>Ошибка: ${data.message}</p>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <p>Ошибка: ${error.message}</p>
                    </div>
                `;
            }
        });

        document.getElementById('statusForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const paymentId = e.target.payment_id.value;
            const resultDiv = document.getElementById('statusResult');
            
            try {
                resultDiv.innerHTML = '<div class="alert alert-info">Проверка статуса...</div>';
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'check_status',
                        payment_id: paymentId
                    })
                });
                
                const data = await response.json();
                
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <p>Статус платежа: ${data.status}</p>
                            <p>Детали:</p>
                            <pre>${JSON.stringify(data.details, null, 2)}</pre>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <p>Ошибка: ${data.message}</p>
                        </div>
                    `;
                }
            } catch (error) {
                resultDiv.innerHTML = `
                    <div class="alert alert-danger">
                        <p>Ошибка: ${error.message}</p>
                    </div>
                `;
            }
        });
    </script>
</body>
</html> 