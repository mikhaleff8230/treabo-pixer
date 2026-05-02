<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Главная</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        
        #app {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 0 auto;
        }
        
        .city-banner { 
            position: fixed; 
            bottom: 20px; 
            left: 20px; 
            right: 20px; 
            max-width: 600px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #e0e0e0;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,.15);
            display: flex;
            align-items: center;
            justify-content: space-between;
            z-index: 1000;
        }
        
        #city-text {
            flex: 1;
            margin-right: 16px;
            color: #333;
        }
        
        #city-text strong {
            color: #667eea;
        }
        
        .city-actions { 
            display: flex; 
            gap: 8px; 
        }
        
        button {
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        #confirm-btn {
            background: #667eea;
            color: white;
        }
        
        #confirm-btn:hover {
            background: #5568d3;
        }
        
        #change-btn {
            background: #f5f5f5;
            color: #333;
        }
        
        #change-btn:hover {
            background: #e0e0e0;
        }
        
        #save-city, #cancel-change {
            padding: 8px 20px;
        }
        
        #save-city {
            background: #4CAF50;
            color: white;
        }
        
        #save-city:hover {
            background: #45a049;
        }
        
        #cancel-change {
            background: #f44336;
            color: white;
        }
        
        #cancel-change:hover {
            background: #da190b;
        }
        
        #city-input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            flex: 1;
            margin-right: 8px;
        }
        
        .hidden { 
            display: none; 
        }
        
        h1 {
            margin-top: 0;
            color: #333;
        }
    </style>
</head>
<body>
    <div id="app">
        <h1>Пример определения города через IpInfo.io</h1>
        <p>Если ваш город определён автоматически, внизу страницы появится баннер с подтверждением.</p>
        <p><strong>Определённый город:</strong> {{ $detectedCity ?? 'Не определён' }}</p>
    </div>

    <div id="city-banner" class="{{ empty($detectedCity) ? 'hidden' : '' }}">
        <div id="city-text">Ваш город — <strong id="city-name">{{ $detectedCity ?? 'Не определён' }}</strong>?</div>
        <div class="city-actions">
            <button id="confirm-btn">Да</button>
            <button id="change-btn">Изменить</button>
        </div>
    </div>

    <div id="city-change" class="hidden">
        <div style="display: flex; align-items: center; gap: 8px; padding: 16px 20px; background: #fff; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,.15);">
            <input id="city-input" placeholder="Введите ваш город"/>
            <button id="save-city">Сохранить</button>
            <button id="cancel-change">Отмена</button>
        </div>
    </div>

    <script>
        (function(){
            function el(id){return document.getElementById(id);}

            var banner = el('city-banner');
            var cityNameEl = el('city-name');
            var confirmBtn = el('confirm-btn');
            var changeBtn = el('change-btn');
            var changeBlock = el('city-change');
            var input = el('city-input');
            var saveBtn = el('save-city');
            var cancelBtn = el('cancel-change');

            function hideBanner(){ if(banner) banner.classList.add('hidden'); }
            function showBanner(){ if(banner) banner.classList.remove('hidden'); }

            confirmBtn && confirmBtn.addEventListener('click', function(){
                var city = cityNameEl.textContent || 'Не указан';
                fetch('{{ route("set-city") }}', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector("meta[name='csrf-token']").getAttribute('content') },
                    body: JSON.stringify({ city: city })
                }).then(function(r){ return r.json(); }).then(function(data){
                    console.log('City saved:', data);
                    hideBanner();
                }).catch(function(err){
                    console.error('Error saving city:', err);
                });
            });

            changeBtn && changeBtn.addEventListener('click', function(){
                banner.classList.add('hidden');
                changeBlock.classList.remove('hidden');
                changeBlock.style.position = 'fixed';
                changeBlock.style.bottom = '20px';
                changeBlock.style.left = '20px';
                changeBlock.style.right = '20px';
                changeBlock.style.maxWidth = '600px';
                changeBlock.style.margin = '0 auto';
            });

            saveBtn && saveBtn.addEventListener('click', function(){
                var city = input.value.trim();
                if(!city) return;
                fetch('{{ route("set-city") }}', {
                    method: 'POST',
                    headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': document.querySelector("meta[name='csrf-token']").getAttribute('content') },
                    body: JSON.stringify({ city: city })
                }).then(function(r){ return r.json(); }).then(function(data){
                    console.log('City saved:', data);
                    changeBlock.classList.add('hidden');
                }).catch(function(err){
                    console.error('Error saving city:', err);
                });
            });

            cancelBtn && cancelBtn.addEventListener('click', function(){
                changeBlock.classList.add('hidden');
                showBanner();
            });
        })();
    </script>
</body>
</html>
