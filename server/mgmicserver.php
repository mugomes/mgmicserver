<?php
# Copyright (C) 2025 Murilo Gomes Julio
# SPDX-License-Identifier: GPL-2.0-only

# Site: https://www.mugomes.com.br

if (!file_exists(__DIR__ . '/config.json')) {
    file_put_contents(__DIR__ . '/config.json', json_encode([
        'port' => '5000',
        'showError' => false
    ], JSON_PRETTY_PRINT));
}

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

if (empty($config['showError'])) {
    ini_set('error_log', __DIR__ . '/error_log');
}

// Obtém o IPv4
$dig_cmd = trim(shell_exec(sprintf('dig +short %s.local', gethostname())));
$dig_ips = explode(PHP_EOL, $dig_cmd);

$host = isset($dig_ips[0]) ? $dig_ips[0] : '';

if (!$host) {
    error_log('Não foi possível resolver o hostname do servidor!' . PHP_EOL);
    exit;
}

$port = $config['port'];

echo 'MGMicServer (pressione \'q\' a qualquer momento para encerrar)' . PHP_EOL;

// Coloca STDIN em modo raw e não ecoa teclas
shell_exec('stty cbreak -echo');
stream_set_blocking(STDIN, false);

$shouldStop = false;

// Função para verificar tecla 'q'
function checkQuitKey()
{
    global $shouldStop;
    $input = fread(STDIN, 1);
    if ($input === 'q') {
        echo PHP_EOL . 'Sinal de encerramento recebido.' . PHP_EOL;
        $shouldStop = true;
    }
}

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($sock, $host, $port);
socket_listen($sock, 1);

echo 'Aguardando conexão do cliente...' . PHP_EOL;

// Loop aguardando cliente ou tecla 'q'
$client = null;
while (!$client && !$shouldStop) {
    checkQuitKey();
    $read = [$sock];
    $write = $except = null;
    // usa select com timeout para não bloquear
    if (socket_select($read, $write, $except, 0, 1000) > 0) {
        $client = socket_accept($sock);
    }
    usleep(1000);
}

if ($shouldStop) {
    echo 'Encerrando antes da conexão do cliente.' . PHP_EOL;
    socket_close($sock);
    shell_exec('stty sane');
    exit;
}

echo 'Cliente conectado!' . PHP_EOL;

// Carrega módulo PulseAudio
$cmdLoad = 'pactl load-module module-echo-cancel sink_name="MGVirtual_Speaker" aec_method=webrtc sink_properties=device.description="MGNoise_Reduction" aec_args="analog_gain_control=0 digital_gain_control=0"';
$moduleId = trim(shell_exec($cmdLoad));
if ($moduleId) printf('Módulo PulseAudio carregado (ID: %s)' . PHP_EOL, $moduleId);

// Inicia arecord
$descriptorspec = [
    1 => ["pipe", "w"],
    2 => ["pipe", "w"]
];
$process = proc_open('arecord -f S16_LE -r 44100 -c 1', $descriptorspec, $pipes);
if (!is_resource($process)) {
    error_log('Erro ao iniciar arecord' . PHP_EOL);
    exit;
}

stream_set_blocking($pipes[1], true);

echo 'Transmitindo áudio...' . PHP_EOL;

// Loop principal de transmissão
while (!$shouldStop && !feof($pipes[1])) {
    checkQuitKey();

    $data = fread($pipes[1], 4096);
    if ($data && $data !== '') {
        socket_write($client, $data);
    }

    usleep(1000);
}

// Fecha tudo
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);
socket_close($client);
socket_close($sock);

// Remove módulo PulseAudio se foi carregado
if (!empty($moduleId)) {
    printf('Removendo módulo PulseAudio (ID: %s)...' . PHP_EOL, $moduleId);
    shell_exec(sprintf('pactl unload-module ', $moduleId));
}

// Restaura o terminal
shell_exec('stty sane');

echo 'Servidor encerrado com sucesso.' . PHP_EOL;
