<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Оплата не удалась</title>
    <link rel="stylesheet" href="/css/app.css">
    <style>
      body { background: #fafafa; font-family: 'Inter', Arial, sans-serif; }
      .fail-container {
        max-width: 400px;
        margin: 60px auto;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 16px #0001;
        padding: 32px 24px;
        text-align: center;
      }
      .fail-title { color: #e74c3c; font-size: 2rem; margin-bottom: 16px; }
      .fail-btn {
        margin-top: 24px;
        background: #FFD600;
        color: #222;
        border: none;
        border-radius: 6px;
        padding: 12px 32px;
        font-size: 1rem;
        cursor: pointer;
        transition: background 0.2s;
      }
      .fail-btn:hover { background: #ffe066; }
    </style>
</head>
<body>
  <div class="fail-container">
  <div class="fail-title">Оплата не удалась</div>
  <div>Платёж не был завершён. Попробуйте ещё раз или выберите другой способ оплаты.</div>
  <button class="fail-btn" onclick="window.location.href='/'">На главную</button>
</div>
<style>
  .fail-title {
    color: #F97316;
    font-size: 1.2rem;
    font-weight: normal;
    margin-bottom: 16px;
  }
  /* ...остальные стили... */
</style>
  <!-- Здесь НЕ очищаем корзину! -->
</body>
</html>