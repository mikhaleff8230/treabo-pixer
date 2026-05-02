<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Оплата успешна</title>
    <link rel="stylesheet" href="/css/app.css"> <!-- подключи свой основной css -->
    <style>
      body { background: #fafafa; font-family: 'Inter', Arial, sans-serif; }
      .success-container {
        max-width: 400px;
        margin: 60px auto;
        background: #fff;
        border-radius: 12px;
        box-shadow: 0 2px 16px #0001;
        padding: 32px 24px;
        text-align: center;
      }
      .success-title { color: #27ae60; font-size: 2rem; margin-bottom: 16px; }
      .success-btn {
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
      .success-btn:hover { background: #ffe066; }
    </style>
</head>
<body>
 <div class="success-container">
  <div class="success-title">Спасибо! Оплата прошла успешно.</div>
  <div>Ваш заказ принят и будет обработан в ближайшее время.</div>
  <button class="success-btn" onclick="window.location.href='/'">На главную</button>
</div>
<style>
  .success-title {
    color: #F97316;
    font-size: 1.2rem;
    font-weight: normal;
    margin-bottom: 16px;
  }
  /* ...остальные стили... */
</style>
  <script>
    // Очистка корзины (если корзина хранится в localStorage и sessionStorage)
    localStorage.removeItem('pixer-cart');
    sessionStorage.removeItem('pixer-cart');
    // Если используется другой способ хранения — добавь нужную логику
  </script>
</body>
</html>