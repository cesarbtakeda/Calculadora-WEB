<?php
// Desativar exposição de informações do servidor
ini_set('expose_php', 'off'); // Remove X-Powered-By
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Configuração de headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
header('Server: WebServer'); // Ofusca a assinatura do servidor
header_remove('X-Powered-By'); // Garante remoção de X-Powered-By

// Validação de origem (CORS)
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = ['https://*.trycloudflare.com', 'https://localhost'];
if ($origin && in_array($origin, $allowed_origins)) {
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization");
}

// Validação de User-Agent e cabeçalhos para bloquear recon (Nmap, etc.)
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$blocked_user_agents = [
    'nmap', 'masscan', 'zmap', 'curl', 'wget', 'python-requests', 'libwww-perl', 'bot', 'spider', 'crawler'
];
$has_valid_headers = isset($_SERVER['HTTP_ACCEPT']) && isset($_SERVER['HTTP_CONNECTION']);
if (empty($user_agent) || !$has_valid_headers || stripos($user_agent, 'mozilla') === false) {
    foreach ($blocked_user_agents as $blocked) {
        if (stripos($user_agent, $blocked) !== false || empty($user_agent)) {
            http_response_code(403);
            die('Acesso bloqueado.');
        }
    }
}

// Validação de rotas
$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$rotas_validas = ['/', '/home', '/sobre', '/contato'];
if (!in_array($request_uri, $rotas_validas)) {
    http_response_code(404);
    include __DIR__ . '/404.html';
    exit;
}

// Proteção CSRF com expiração de token
session_start();
$csrf_expiry = 3600; // 1 hora
if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_time']) || time() - $_SESSION['csrf_time'] > $csrf_expiry) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_time'] = time();
}
$csrf_token = $_SESSION['csrf_token'];

// Rate limiting para requisições POST
$ip = $_SERVER['REMOTE_ADDR'];
$cache_file = __DIR__ . '/cache/rate_limit_' . md5($ip) . '.txt';
$max_requests = 10; // Máximo de requisições por minuto
$time_window = 60; // 1 minuto
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (file_exists($cache_file)) {
        $data = json_decode(file_get_contents($cache_file), true);
        $timestamp = $data['timestamp'] ?? 0;
        $count = $data['count'] ?? 0;
        if (time() - $timestamp < $time_window) {
            if ($count >= $max_requests) {
                http_response_code(429);
                die('Muitas requisições. Tente novamente mais tarde.');
            }
            $count++;
        } else {
            $count = 1;
            $timestamp = time();
        }
    } else {
        $count = 1;
        $timestamp = time();
    }
    file_put_contents($cache_file, json_encode(['count' => $count, 'timestamp' => $timestamp]));
}

// Sanitização de entrada
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Validação de POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        die('Erro: Token CSRF inválido.');
    }
    // Sanitizar entradas
    foreach ($_POST as $key => $value) {
        $_POST[$key] = sanitizeInput($value);
    }
}
?>


<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <title>Calculadora de Média - Facens</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --cor-primaria: #4B0082;
            --cor-primaria-clara: #6a0dad;
            --cor-fundo: #f4f0f8;
            --cor-texto: #2d003d;
            --cor-borda: #a674c2;
            --cor-input: #e8dff1;
            --cor-icone: #4B0082;
            --cor-fundo-escuro: #2d003d;
            --cor-texto-escuro: #f4f0f8;
            --cor-input-escuro: #4B0082;
            --cor-borda-escura: #6a0dad;
        }

        body {
            background: var(--cor-fundo);
            color: var(--cor-texto);
            line-height: 1.5;
            padding: 20px;
            transition: background 0.3s ease, color 0.3s ease;
        }

        body.dark-mode {
            background: var(--cor-fundo-escuro);
            color: var(--cor-texto-escuro);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(75, 0, 130, 0.1);
            padding: 20px 40px 40px;
            transition: background 0.3s ease;
        }

        body.dark-mode .container {
            background: #3c0061;
        }

        header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
        }

        header h1 {
            font-weight: 700;
            color: var(--cor-primaria);
            font-size: 2.4rem;
            letter-spacing: 1.2px;
        }

        body.dark-mode header h1 {
            color: var(--cor-texto-escuro);
        }

        .theme-toggle {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.5rem;
            color: var(--cor-primaria);
            transition: color 0.3s ease;
        }

        body.dark-mode .theme-toggle {
            color: var(--cor-texto-escuro);
        }

        .theme-toggle .sun-icon {
            display: inline;
        }

        .theme-toggle .moon-icon {
            display: none;
        }

        body.dark-mode .theme-toggle .sun-icon {
            display: none;
        }

        body.dark-mode .theme-toggle .moon-icon {
            display: inline;
        }

        .ad-banner-top,
        .ad-banner-side {
            background: linear-gradient(135deg, var(--cor-input), #f0e6f6);
            border: 2px dashed var(--cor-borda);
            color: var(--cor-primaria);
            font-style: italic;
            font-size: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            border-radius: 8px;
        }

        body.dark-mode .ad-banner-top,
        body.dark-mode .ad-banner-side {
            background: linear-gradient(135deg, var(--cor-input-escuro), #3c0061);
            border: 2px dashed var(--cor-borda-escura);
            color: var(--cor-texto-escuro);
        }

        .ad-banner-top {
            width: 100%;
            height: 90px;
            margin-bottom: 25px;
        }

        .ad-banner-side {
            width: 200px;
            height: 600px;
            position: sticky;
            top: 20px;
        }

        .content-wrapper {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }

        .main-content {
            flex: 1;
        }

        form {
            background: #fff;
            padding: 20px 25px;
            border-radius: 6px;
            border: 1px solid var(--cor-borda);
            transition: background 0.3s ease, border 0.3s ease;
        }

        body.dark-mode form {
            background: #3c0061;
            border: 1px solid var(--cor-borda-escura);
        }

        .materia {
            border-bottom: 1px solid var(--cor-borda);
            padding-bottom: 18px;
            margin-bottom: 18px;
            transition: all 0.3s ease;
        }

        body.dark-mode .materia {
            border-bottom: 1px solid var(--cor-borda-escura);
        }

        .materia:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .materia h2 {
            font-size: 1.3rem;
            color: var(--cor-primaria-clara);
            margin-bottom: 12px;
            font-weight: 600;
        }

        body.dark-mode .materia h2 {
            color: var(--cor-texto-escuro);
        }

        .materia-nome-container {
            position: relative;
            display: flex;
            align-items: center;
            background: linear-gradient(135deg, var(--cor-input), #f0e6f6);
            border: 1px solid var(--cor-borda);
            border-radius: 6px;
            padding: 5px;
            margin-bottom: 20px;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        body.dark-mode .materia-nome-container {
            background: linear-gradient(135deg, var(--cor-input-escuro), #3c0061);
            border: 1px solid var(--cor-borda-escura);
        }

        .materia-nome-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(75, 0, 130, 0.2);
        }

        body.dark-mode .materia-nome-container:hover {
            box-shadow: 0 2px 8px rgba(244, 240, 248, 0.2);
        }

        .materia-nome-container::before {
            content: "📚";
            font-size: 1.2rem;
            margin-right: 8px;
            color: var(--cor-icone);
        }

        body.dark-mode .materia-nome-container::before {
            color: var(--cor-texto-escuro);
        }

        .materia-nome-container input {
            border: none;
            background: transparent;
            width: 100%;
            padding: 8px;
            font-size: 1rem;
            color: var(--cor-texto);
        }

        body.dark-mode .materia-nome-container input {
            color: var(--cor-texto-escuro);
        }

        .materia-nome-container input:focus {
            outline: none;
        }

        .notas {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 12px;
        }

        .notas input {
            width: 60px;
            padding: 6px 8px;
            font-size: 1rem;
            text-align: center;
            border: 1px solid var(--cor-borda);
            border-radius: 4px;
            background: linear-gradient(135deg, var(--cor-input), #f0e6f6);
            color: var(--cor-texto);
            -webkit-appearance: none;
            -moz-appearance: textfield;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        body.dark-mode .notas input {
            border: 1px solid var(--cor-borda-escura);
            background: linear-gradient(135deg, var(--cor-input-escuro), #3c0061);
            color: var(--cor-texto-escuro);
        }

        .notas input:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(75, 0, 130, 0.2);
        }

        body.dark-mode .notas input:hover {
            box-shadow: 0 2px 8px rgba(244, 240, 248, 0.2);
        }

        input[type=number]::-webkit-inner-spin-button,
        input[type=number]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        button {
            background: var(--cor-primaria);
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 4px;
            margin-right: 10px;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        body.dark-mode button {
            background: var(--cor-primaria-clara);
        }

        button:hover {
            background: var(--cor-primaria-clara);
        }

        body.dark-mode button:hover {
            background: var(--cor-primaria);
        }

        .media {
            font-weight: bold;
            background: var(--cor-input);
        }

        body.dark-mode .media {
            background: var(--cor-input-escuro);
            color: var(--cor-texto-escuro);
        }

        footer {
            text-align: center;
            margin-top: 40px;
            color: var(--cor-primaria-clara);
        }

        body.dark-mode footer {
            color: var(--cor-texto-escuro);
        }

        img {
            float: left;
            margin-right: 10px;
        }

        #popupAd {
            display: none;
            position: fixed;
            bottom: 20px;
            left: 20px;
            right: 20px;
            background: linear-gradient(135deg, #f0e6f6, var(--cor-input));
            border: 2px solid var(--cor-borda);
            padding: 15px;
            color: var(--cor-primaria);
            border-radius: 10px;
            font-style: italic;
            font-size: 1rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            z-index: 1000;
        }

        body.dark-mode #popupAd {
            background: linear-gradient(135deg, #3c0061, var(--cor-input-escuro));
            border: 2px solid var(--cor-borda-escura);
            color: var(--cor-texto-escuro);
        }

        #popupAd .fechar {
            position: absolute;
            top: 5px;
            right: 10px;
            font-size: 20px;
            color: var(--cor-primaria);
            cursor: pointer;
        }

        body.dark-mode #popupAd .fechar {
            color: var(--cor-texto-escuro);
        }

        .feedback {
            color: var(--cor-primaria);
            font-size: 0.9rem;
            margin-top: 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        body.dark-mode .feedback {
            color: var(--cor-texto-escuro);
        }

        .feedback.visible {
            opacity: 1;
        }

        @media (max-width: 768px) {
            .ad-banner-side {
                display: none;
            }

            #popupAd {
                display: block;
            }

            .content-wrapper {
                flex-direction: column;
            }

            header h1 {
                font-size: 1.6rem;
            }

            form {
                padding: 15px;
            }

            .notas {
                flex-wrap: wrap;
                gap: 10px;
                justify-content: center;
            }

            .notas input {
                width: 48%;
                min-width: 100px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <img src="images/logo.png" alt="Logo CMU" width="200" loading="lazy">
            <h1>Calculadora de Média Universitária - Facens</h1>
            <button class="theme-toggle" onclick="toggleTheme()">
                <span class="sun-icon">☀️</span>
                <span class="moon-icon">🌙</span>
            </button>
        </header>

        <div class="ad-banner-top">Espaço para anúncio topo</div>

        <div class="content-wrapper">
            <main class="main-content">
                <form id="formNotas" action="#" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                    <div id="materiasContainer">
                        <div class="materia visible" id="materia1">
                            <h2>Matéria 1</h2>
                            <div class="materia-nome-container">
                                <input type="text" name="materia1_nome" placeholder="Nome da matéria" required aria-label="Nome da matéria 1" oninput="validateTextInput(this)">
                            </div>
                            <div class="notas">
                                <input type="number" name="materia1_nota1" placeholder="AC1" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" required aria-label="Nota AC1">
                                <input type="number" name="materia1_nota2" placeholder="AC2" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" required aria-label="Nota AC2">
                                <input type="number" name="materia1_nota3" placeholder="PA" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" required aria-label="Nota PA">
                                <input type="number" name="materia1_nota4" placeholder="AG" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" required aria-label="Nota AG">
                                <input type="number" name="materia1_nota5" placeholder="AS" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" aria-label="Nota AS">
                                <input type="text" name="materia1_media" class="media" placeholder="Média" readonly aria-label="Média da matéria 1">
                            </div>
                        </div>
                    </div>

                    <button type="button" onclick="adicionarMateria()">Adicionar matéria</button>
                    <button type="button" onclick="removerMateria()">Remover matéria</button>
                    <p id="msg" class="feedback"></p>
                </form>

                <h2>Média Geral</h2>
                <p id="mediaGeral" style="font-size: 22px;">-</p>
            </main>

            <aside class="ad-banner-side">Espaço para anúncio lateral</aside>
        </div>

        <footer>
            © 2025 Calculadora de Média - Todos os direitos reservados
        </footer>
    </div>

    <div id="popupAd">
        <span class="fechar" onclick="fecharPopup()">×</span>
        Espaço para anúncio no mobile
    </div>

    <script>
        let materiasAdicionadas = 1;

        function showFeedback(message) {
            const msg = document.getElementById('msg');
            msg.textContent = message;
            msg.classList.add('visible');
            setTimeout(() => msg.classList.remove('visible'), 3000);
        }

        function validateTextInput(input) {
            input.value = input.value.replace(/[^a-zA-Z\s]/g, '');
        }

        function validateNumberInput(input) {
            let valueStr = input.value.replace(',', '.');
            valueStr = valueStr.replace(/[^0-9.]/g, '');
            const parts = valueStr.split('.');
            if (parts.length > 2) {
                valueStr = parts[0] + '.' + parts.slice(1).join('');
            }
            if (parts[1] && parts[1].length > 2) {
                valueStr = parts[0] + '.' + parts[1].slice(0, 2);
            }
            input.value = valueStr;

            let value = parseFloat(valueStr);
            if (isNaN(value) || value < 0) {
                input.value = '';
            } else if (value > 10) {
                input.value = '10';
            } else if (value > 9.99 && value < 10) {
                input.value = '9.99';
            }
        }

        function adicionarMateria() {
            if (materiasAdicionadas >= 8) {
                showFeedback("Limite de 8 matérias atingido.");
                return;
            }
            materiasAdicionadas++;

            const container = document.getElementById('materiasContainer');
            const novaMateria = document.createElement('div');
            novaMateria.className = 'materia visible';
            novaMateria.id = `materia${materiasAdicionadas}`;
            novaMateria.innerHTML = `
                <h2>Matéria ${materiasAdicionadas}</h2>
                <div class="materia-nome-container">
                    <input type="text" name="materia${materiasAdicionadas}_nome" placeholder="Nome da matéria" required aria-label="Nome da matéria ${materiasAdicionadas}" oninput="validateTextInput(this)">
                </div>
                <div class="notas">
                    <input type="number" name="materia${materiasAdicionadas}_nota1" placeholder="AC1" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" required aria-label="Nota AC1">
                    <input type="number" name="materia${materiasAdicionadas}_nota2" placeholder="AC2" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" required aria-label="Nota AC2">
                    <input type="number" name="materia${materiasAdicionadas}_nota3" placeholder="PA" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" required aria-label="Nota PA">
                    <input type="number" name="materia${materiasAdicionadas}_nota4" placeholder="AG" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" required aria-label="Nota AG">
                    <input type="number" name="materia${materiasAdicionadas}_nota5" placeholder="AS" min="0" step="0.01" oninput="validateNumberInput(this); calcularMedia(this)" aria-label="Nota AS">
                    <input type="text" name="materia${materiasAdicionadas}_media" class="media" placeholder="Média" readonly aria-label="Média da matéria ${materiasAdicionadas}">
                </div>
            `;
            container.appendChild(novaMateria);
            showFeedback(`Matéria ${materiasAdicionadas} adicionada com sucesso!`);
        }

        function removerMateria() {
            if (materiasAdicionadas <= 1) {
                showFeedback("Não há mais matérias para remover.");
                return;
            }
            const container = document.getElementById('materiasContainer');
            const ultimaMateria = document.getElementById(`materia${materiasAdicionadas}`);
            if (ultimaMateria) {
                container.removeChild(ultimaMateria);
                materiasAdicionadas--;
                showFeedback(`Matéria ${materiasAdicionadas + 1} removida.`);
                calcularMediaGeral();
            }
        }

        function calcularMedia(input) {
            const notasDiv = input.closest(".notas");
            const inputs = notasDiv.querySelectorAll('input[type="number"]');
            const pesos = [1.5, 3, 4.5, 1];
            let notas = Array.from(inputs).slice(0, 4).map(i => parseFloat(i.value) || 0);
            const notaAS = parseFloat(inputs[4].value) || 0;

            let melhorIndex = -1;
            let melhorGanho = 0;
            for (let i = 0; i < 4; i++) {
                const ganho = (notaAS * pesos[i]) - (notas[i] * pesos[i]);
                if (ganho > melhorGanho) {
                    melhorGanho = ganho;
                    melhorIndex = i;
                }
            }

            if (melhorIndex >= 0) notas[melhorIndex] = notaAS;

            const somaPonderada = notas.reduce((sum, nota, i) => sum + nota * pesos[i], 0);
            const somaPesos = pesos.reduce((sum, peso) => sum + peso, 0);
            const media = (somaPonderada / somaPesos).toFixed(2);
            notasDiv.querySelector(".media").value = media;

            calcularMediaGeral();
        }

        function calcularMediaGeral() {
            const medias = document.querySelectorAll(".materia.visible .media");
            const soma = Array.from(medias).reduce((sum, el) => sum + (parseFloat(el.value) || 0), 0);
            const mediaGeral = medias.length > 0 ? (soma / medias.length).toFixed(2) : "-";
            document.getElementById("mediaGeral").textContent = mediaGeral;
        }

        function fecharPopup() {
            document.getElementById("popupAd").style.display = "none";
        }

        function toggleTheme() {
            document.body.classList.toggle('dark-mode');
            localStorage.setItem('theme', document.body.classList.contains('dark-mode') ? 'dark' : 'light');
        }

        document.addEventListener('DOMContentLoaded', () => {
            if (localStorage.getItem('theme') === 'dark') {
                document.body.classList.add('dark-mode');
            }
        });
    </script>
</body>
</html>
