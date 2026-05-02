<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require '../vendor/autoload.php';
require '../bootstrap/app.php';

// Включаем буферизацию вывода для перехвата всех ошибок
ob_start();

// Функция для форматирования ошибок
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    $error = [
        'type' => $errno,
        'message' => $errstr,
        'file' => $errfile,
        'line' => $errline,
        'time' => date('Y-m-d H:i:s'),
        'trace' => debug_backtrace()
    ];
    
    echo "<script>addDebugMessage(" . json_encode($error) . ");</script>";
    return false;
}

// Устанавливаем обработчик ошибок
set_error_handler("customErrorHandler");

// Обработчик исключений
function customExceptionHandler($exception) {
    $error = [
        'type' => get_class($exception),
        'message' => $exception->getMessage(),
        'file' => $exception->getFile(),
        'line' => $exception->getLine(),
        'time' => date('Y-m-d H:i:s'),
        'trace' => $exception->getTrace()
    ];
    
    echo "<script>addDebugMessage(" . json_encode($error) . ");</script>";
}

set_exception_handler("customExceptionHandler");
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Тестирование ЮKassa</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .test-button {
            display: inline-block;
            padding: 10px 20px;
            margin: 10px;
            background-color: #4CAF50;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .test-button:hover {
            background-color: #45a049;
        }
        .test-button:disabled {
            background-color: #cccccc;
            cursor: not-allowed;
        }
        .result {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            display: none;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background-color: #f5f5f5;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        #paymentUrl {
            margin-top: 20px;
            padding: 20px;
            background-color: #e8f5e9;
            border-radius: 5px;
            display: none;
        }
        #debugWindow {
            background-color: #1e1e1e;
            color: #ffffff;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Consolas', monospace;
            margin-top: 20px;
            max-height: 400px;
            overflow-y: auto;
        }
        .debug-entry {
            margin-bottom: 15px;
            padding: 10px;
            border-left: 3px solid #666;
        }
        .debug-entry.error {
            border-left-color: #ff4444;
        }
        .debug-entry.warning {
            border-left-color: #ffbb33;
        }
        .debug-entry pre {
            margin: 5px 0;
            white-space: pre-wrap;
        }
        .debug-time {
            color: #888;
            font-size: 0.9em;
        }
        .debug-type {
            color: #4CAF50;
            font-weight: bold;
        }
        .debug-message {
            color: #fff;
        }
        .debug-file {
            color: #2196F3;
        }
        .debug-trace {
            color: #888;
            font-size: 0.9em;
            margin-top: 5px;
            padding-left: 20px;
        }
        .clear-debug {
            background-color: #ff4444;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
            float: right;
        }
        .clear-debug:hover {
            background-color: #cc0000;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Тестирование методов ЮKassa</h1>
        <p>Базовый URL: <?php echo config('app.url'); ?></p>
        
        <div>
            <button class="test-button" onclick="runTest('create')">Создать тестовый платеж</button>
            <button class="test-button" onclick="runTest('check')" id="checkBtn" disabled>Проверить статус платежа</button>
            <button class="test-button" onclick="runTest('cancel')" id="cancelBtn" disabled>Отменить платеж</button>
            <button class="test-button" onclick="runTest('refund')" id="refundBtn" disabled>Сделать возврат</button>
        </div>

        <div id="paymentUrl"></div>
        <div id="result" class="result"></div>
    </div>

    <div class="container">
        <h2>Отладочная информация <button class="clear-debug" onclick="clearDebug()">Очистить</button></h2>
        <div id="debugWindow"></div>
    </div>

    <script>
    let currentPaymentId = null;

    function addDebugMessage(error) {
        const debugWindow = document.getElementById('debugWindow');
        const entry = document.createElement('div');
        entry.className = 'debug-entry' + (error.type.includes('Error') ? ' error' : ' warning');
        
        let traceHtml = '';
        if (error.trace) {
            traceHtml = '<div class="debug-trace">Stack Trace:<br>' + 
                error.trace.map(t => {
                    return `${t.file}:${t.line} - ${t.function}()`;
                }).join('<br>') + 
                '</div>';
        }

        entry.innerHTML = `
            <span class="debug-time">[${error.time}]</span>
            <span class="debug-type">${error.type}</span>
            <div class="debug-message">${error.message}</div>
            <div class="debug-file">${error.file}:${error.line}</div>
            ${traceHtml}
        `;
        
        debugWindow.insertBefore(entry, debugWindow.firstChild);
    }

    function clearDebug() {
        document.getElementById('debugWindow').innerHTML = '';
    }

    async function runTest(method) {
        const resultDiv = document.getElementById('result');
        const paymentUrlDiv = document.getElementById('paymentUrl');
        
        resultDiv.style.display = 'block';
        resultDiv.innerHTML = 'Выполняется тест...';
        
        try {
            const response = await fetch(`test-yookassa-cases.php?method=${method}${currentPaymentId ? '&payment_id=' + currentPaymentId : ''}`);
            const result = await response.json();
            
            // Добавляем информацию в отладочное окно
            addDebugMessage({
                type: 'Info',
                message: `Выполнен запрос ${method}`,
                file: 'test-yookassa-cases.php',
                line: 0,
                time: new Date().toISOString(),
                trace: [{
                    file: 'test-yookassa-interface.php',
                    line: 0,
                    function: 'runTest'
                }]
            });
            
            if (result.success) {
                resultDiv.innerHTML = `
                    <h3 class="success">✅ Тест успешно выполнен</h3>
                    <table>
                        <tr>
                            <th>Параметр</th>
                            <th>Значение</th>
                        </tr>
                        ${Object.entries(result.data).map(([key, value]) => `
                            <tr>
                                <td>${key}</td>
                                <td>${value}</td>
                            </tr>
                        `).join('')}
                    </table>
                `;

                if (method === 'create') {
                    currentPaymentId = result.data.id;
                    document.getElementById('checkBtn').disabled = false;
                    document.getElementById('cancelBtn').disabled = false;
                    document.getElementById('refundBtn').disabled = false;

                    if (result.data.payment_url) {
                        paymentUrlDiv.style.display = 'block';
                        paymentUrlDiv.innerHTML = `
                            <h3>Ссылка на оплату:</h3>
                            <a href="${result.data.payment_url}" target="_blank" class="test-button">
                                Перейти к оплате
                            </a>
                        `;
                    }
                }
            } else {
                resultDiv.innerHTML = `
                    <h3 class="error">❌ Ошибка при выполнении теста</h3>
                    <p>${result.error}</p>
                `;
                
                // Добавляем ошибку в отладочное окно
                addDebugMessage({
                    type: 'Error',
                    message: result.error,
                    file: 'test-yookassa-cases.php',
                    line: 0,
                    time: new Date().toISOString(),
                    trace: [{
                        file: 'test-yookassa-interface.php',
                        line: 0,
                        function: 'runTest'
                    }]
                });
            }
        } catch (error) {
            resultDiv.innerHTML = `
                <h3 class="error">❌ Ошибка при выполнении теста</h3>
                <p>${error.message}</p>
            `;
            
            // Добавляем ошибку в отладочное окно
            addDebugMessage({
                type: 'Error',
                message: error.message,
                file: 'test-yookassa-interface.php',
                line: 0,
                time: new Date().toISOString(),
                trace: error.stack ? error.stack.split('\n') : []
            });
        }
    }
    </script>
</body>
</html> 