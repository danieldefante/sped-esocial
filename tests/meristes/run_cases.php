<?php
// Casos dos eventos que o Meristes envia (S-1.3), no formato dos pilotos (igreja imune, ministro 781,
// sócio 723 de empresa do Simples). Cada caso é montado pela lib, assinado com o certificado de teste
// da lib e validado no XSD de schemes/v_S_01_03_00 (Factory::sign). Com um segundo argumento, valida
// também em cada pacote oficial dessa pasta (uma subpasta por pacote).
// Uso: php tests/meristes/run_cases.php [dir_pacotes_oficiais]
require __DIR__ . '/../../bootstrap.php';
use NFePHP\Common\Certificate;
use NFePHP\eSocial\Event;
libxml_use_internal_errors(true);
$cert = Certificate::readPfx(
    file_get_contents(__DIR__ . '/../../examples/Fake/v_S_01_03_00/expired_certificate.pfx'),
    'associacao'
);
$falhas = 0;
foreach (glob(__DIR__ . '/cases/*.json') as $file) {
    $case = json_decode(file_get_contents($file));
    $nome = basename($file, '.json');
    try {
        $xml = Event::{$case->event}(json_encode($case->config), $case->std, $cert)->toXml();
        echo "OK    $nome (lib + XSD da lib)\n";
    } catch (\Throwable $e) {
        $falhas++;
        echo "FALHA $nome: " . str_replace("\n", ' | ', $e->getMessage()) . "\n";
        continue;
    }
    foreach (empty($argv[1]) ? [] : glob($argv[1] . '/*', GLOB_ONLYDIR) as $pacote) {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $ok = $dom->schemaValidate("$pacote/{$case->xsd}.xsd");
        $erros = array_map(fn($e) => trim($e->message), libxml_get_errors());
        libxml_clear_errors();
        if (!$ok) {
            $falhas++;
        }
        echo ($ok ? 'OK   ' : 'FALHA') . " $nome " . basename($pacote) . ($ok ? '' : ': ' . implode(' | ', array_slice($erros, 0, 3))) . "\n";
    }
}
exit($falhas > 0 ? 1 : 0);
