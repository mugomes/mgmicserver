<?php
# Copyright (C) 2025 Murilo Gomes Julio
# SPDX-License-Identifier: GPL-2.0-only

# Site: https://www.mugomes.com.br

error_reporting(E_ALL);

ini_set('max_execution_time', 300);
ini_set('memory_limit', '256M');

if (!file_exists(__DIR__ . '/config.json')) {
    file_put_contents(__DIR__ . '/config.json', json_encode([
        'hostname' => '',
        'port' => '5000',
        'inputMicrophone' => false,
        'showError' => false
    ], JSON_PRETTY_PRINT));
    die('Adicione o nome da sua máquina (servidor) no arquivo config.json gerado!' . PHP_EOL);
}

$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);

if (empty($config['hostname'])) {
    die('Adicione o nome da sua máquina (servidor) no arquivo config.json gerado!' . PHP_EOL);
}

if (empty($config['showError'])) {
    ini_set('error_log', __DIR__ . '/error_log');
}

$port = $config['port'];

// Obtém o IPv4
$avahi_cmd = "avahi-resolve-host-name -4 " . escapeshellarg(sprintf('%s.local', $config['hostname'])) . " 2>/dev/null";
$avahi_out = trim(shell_exec($avahi_cmd));
$server = false;
if ($avahi_out) {
    $avahi_parts = preg_split('/\s+/', $avahi_out);
    $avahi_ip = end($avahi_parts);

    if (filter_var($avahi_ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $server = $avahi_ip;
    }
}

if (!$server) {
    error_log('Não foi possível resolver o hostname!' . PHP_EOL);
    exit;
}

echo 'Criando sink virtual PulseAudio...' . PHP_EOL;
exec('pactl unload-module module-null-sink 2>/dev/null');
exec('pactl load-module module-null-sink sink_name=MGNetworkAudio sink_properties=device.description=MGNetwork_Audio');

if (!empty($config['inputMicrophone'])) {
    exec('pactl load-module module-virtual-source source_name=MGNetworkMic master=MGNetworkAudio.monitor source_properties="device.description=MGNetwork_Microphone"');
}

sleep(1);

echo 'Conectando ao servidor...' . PHP_EOL;

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$sock) {
    error_log(sprintf('Erro ao criar socket: %s', socket_strerror(socket_last_error())) . PHP_EOL);
    exit;
}

if (!@socket_connect($sock, $server, $port)) {
    error_log(sprintf('Erro ao conectar ao servidor: %s', socket_strerror(socket_last_error($sock))) . PHP_EOL);
    exit;
}

// Abre pacat para reprodução
echo 'Enviando áudio para o dispositivo virtual...' . PHP_EOL;

$descriptorspec = [
    0 => ["pipe", "r"],  // stdin
    1 => ["pipe", "w"],  // stdout
    2 => ["pipe", "w"]   // stderr
];

$process = proc_open(
    'pacat --playback --raw --rate=44100 --channels=1 --format=s16le --device=MGNetworkAudio',
    $descriptorspec,
    $pipes
);

if (!is_resource($process)) {
    error_log('Erro ao iniciar pacat' . PHP_EOL);
    exit;
}

// Configura STDIN para modo raw e não ecoa teclas
shell_exec('stty cbreak -echo');
stream_set_blocking(STDIN, false);
stream_set_blocking($pipes[0], true);

echo 'Recebendo áudio... pressione \'q\' para encerrar.' . PHP_EOL;

$shouldStop = false;

try {
    while (!$shouldStop) {
        // Verifica tecla 'q'
        $input = fread(STDIN, 1);
        if ($input === 'q') {
            echo PHP_EOL . 'Sinal de encerramento recebido.' . PHP_EOL;
            $shouldStop = true;
            break;
        }

        // Lê dados do servidor
        $data = socket_read($sock, 4096);
        if ($data === false || $data === '') {
            break;
        }

        fwrite($pipes[0], $data);
        usleep(1000); // evita 100% CPU
    }
} catch (Exception $e) {
    echo 'Fluxo de áudio finalizado.' . PHP_EOL;
}

// Encerra tudo
fclose($pipes[0]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($process);
socket_close($sock);

exec('pactl unload-module module-null-sink 2>/dev/null');

if (!empty($config['inputMicrophone'])) {
    exec('pactl unload-module "$(pactl list short modules | grep MGNetworkMic | awk \'{print $1}\')" 2>/dev/null');
}

// Restaura o terminal
shell_exec('stty sane');

echo 'Cliente encerrado.' . PHP_EOL;
