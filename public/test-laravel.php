# Создайте тестовый файл
cat > /var/www/sancan.ru/pixer-api/public/test-laravel.php << 'EOF'
<?php
echo "Laravel работает!";
echo "<br>PHP версия: " . phpversion();
echo "<br>Путь: " . __DIR__;
?>
EOF

# Проверьте в браузере: https://sancan.ru/test-laravel.php