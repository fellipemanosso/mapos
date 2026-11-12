<?php

/** Cliente restrito às operações da instância configurada. Nunca devolve erros brutos da API. */
class Evolution_api
{
    public function executar(string $url, string $instancia, string $chave, string $acao): array
    {
        $rotas = ['status' => ['GET', 'connectionState'], 'conectar' => ['GET', 'connect'],
            'reiniciar' => ['POST', 'restart'], 'desconectar' => ['DELETE', 'logout']];
        if (! isset($rotas[$acao])) {
            throw new InvalidArgumentException('Operação não permitida.');
        }
        [$metodo, $rota] = $rotas[$acao];
        [$config, $erros] = evolutionConfiguracao(['url' => $url, 'instancia' => $instancia, 'chave' => $chave]);
        if ($erros !== [] || $chave === '') {
            throw new RuntimeException('Confira a configuração da Evolution.');
        }
        return $this->requisicao($metodo, $config['url'] . '/instance/' . $rota . '/' . rawurlencode($instancia), $chave);
    }

    protected function requisicao(string $metodo, string $url, string $chave): array
    {
        $curl = curl_init($url);
        $conteudo = '';
        curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_HTTPHEADER => ['apikey: ' . $chave, 'Accept: application/json'],
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $parte) use (&$conteudo): int {
                if (strlen($conteudo) + strlen($parte) > 1048576) {
                    return 0;
                }
                $conteudo .= $parte;
                return strlen($parte);
            }]);
        $ok = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        if ($ok === false) {
            throw new RuntimeException('Não foi possível acessar a Evolution. Confira o endereço e a disponibilidade do servidor.');
        }
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException(match ($status) {
                401, 403 => 'A Evolution recusou a chave de acesso.',
                404 => 'Instância ou operação não encontrada. Confira o nome e a versão da Evolution.',
                default => 'A Evolution não concluiu a operação. Tente novamente mais tarde.',
            });
        }
        $dados = json_decode($conteudo, true);
        if (! is_array($dados) || ! empty($dados['error'])) {
            throw new RuntimeException('A Evolution não confirmou a operação. Confira a instância no painel da Evolution.');
        }
        return $dados;
    }
}
