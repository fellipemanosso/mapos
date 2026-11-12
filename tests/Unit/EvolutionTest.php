<?php

use PHPUnit\Framework\TestCase;

require_once APPPATH . 'helpers/evolution_helper.php';
require_once APPPATH . 'libraries/Evolution_api.php';
require_once APPPATH . 'core/MY_Controller.php';
require_once APPPATH . 'controllers/Whatsapp.php';
require_once APPPATH . 'models/Evolution_model.php';
require_once BASEPATH . 'libraries/Encryption.php';

final class EvolutionTest extends TestCase
{
    public function testCredencialCifradaPodeSerRecuperadaERecusaAdulteracao(): void
    {
        $criptografia = new CI_Encryption(['driver' => 'openssl', 'key' => str_repeat('a', 32)]);
        $cifrada = $criptografia->encrypt('chave-de-teste');
        $this->assertIsString($cifrada);
        $this->assertStringNotContainsString('chave-de-teste', $cifrada);
        $this->assertSame('chave-de-teste', $criptografia->decrypt($cifrada));
        $this->assertFalse($criptografia->decrypt('adulterado' . $cifrada));
    }

    public function testValidaServidorInstanciaEChave(): void
    {
        [$dados, $erros] = evolutionConfiguracao(['url' => 'https://evo.example.com/api/', 'instancia' => 'esposa-01', 'chave' => 'token']);
        $this->assertSame([], $erros);
        $this->assertSame('https://evo.example.com/api', $dados['url']);
        foreach (['http://evo.example.com', 'file:///etc/passwd', 'https://user:pass@example.com', 'https://example.com?chave=x', 'https://example.com#x'] as $url) {
            [, $erros] = evolutionConfiguracao(['url' => $url, 'instancia' => '../outra', 'chave' => "x\r\nHost: outro"]);
            $this->assertArrayHasKey('url', $erros);
            $this->assertArrayHasKey('instancia', $erros);
            $this->assertArrayHasKey('chave', $erros);
        }
        [, $erros] = evolutionConfiguracao(['url' => [], 'instancia' => [], 'chave' => []]);
        $this->assertCount(3, $erros);
    }

    public function testOperacoesUsamSomenteRotasDaInstanciaConfigurada(): void
    {
        $api = new class extends Evolution_api {
            public array $chamada = [];
            protected function requisicao(string $metodo, string $url, string $chave): array
            {
                $this->chamada = [$metodo, $url, $chave];
                return ['instance' => ['state' => 'open']];
            }
        };
        foreach (['status' => ['GET', 'connectionState'], 'conectar' => ['GET', 'connect'], 'reiniciar' => ['POST', 'restart'], 'desconectar' => ['DELETE', 'logout']] as $acao => [$metodo, $rota]) {
            $this->assertSame('Conectado', evolutionEstado($api->executar('https://evo.example.com', 'minha', 'privado', $acao))['label']);
            $this->assertSame([$metodo, 'https://evo.example.com/instance/' . $rota . '/minha', 'privado'], $api->chamada);
        }
        $this->expectException(InvalidArgumentException::class);
        $api->executar('https://evo.example.com', 'minha', 'privado', 'delete');
    }

    public function testRecusaChaveVaziaAntesDeChamarRede(): void
    {
        $this->expectException(RuntimeException::class);
        (new Evolution_api())->executar('https://evo.example.com', 'minha', '', 'status');
    }

    public function testQrAceitaSomentePngEmBase64(): void
    {
        $png = 'data:image/png;base64,' . base64_encode("\x89PNG\r\n\x1a\nfixture");
        $this->assertSame($png, evolutionQr(['base64' => $png]));
        $this->assertSame($png, evolutionQr(['qrcode' => ['base64' => $png]]));
        foreach (['https://example.com/qr.png', 'data:image/svg+xml;base64,PHN2Zz4=', 'data:image/png;base64,bm90LXBuZw==', []] as $valor) {
            $this->assertNull(evolutionQr(['base64' => $valor]));
        }
        $this->assertSame('Desconectado', evolutionEstado(['instance' => ['state' => 'close']])['label']);
        $this->assertSame('Conectando', evolutionEstado(['instance' => ['status' => 'connecting']])['label']);
        $this->assertSame('Estado não confirmado', evolutionEstado([])['label']);
    }

    public function testConfiguracaoEUnicaENaoGuardaChaveEmCampoPublico(): void
    {
        $db = maposTestDatabase();
        $db->query('CREATE TABLE evolution_config (id INTEGER PRIMARY KEY, url TEXT, instancia TEXT, chave_cifrada TEXT)');
        $modelo = new Evolution_model();
        $modelo->db = $db;
        $this->assertNull($modelo->configuracao());
        $this->assertTrue($modelo->salvar('https://evo.example.com', 'minha', 'cifrada'));
        $this->assertTrue($modelo->salvar('https://evo.example.com', 'outra', 'nova-cifrada'));
        $this->assertSame(1, $db->count_all('evolution_config'));
        $this->assertSame('outra', $modelo->configuracao()->instancia);
        $this->assertSame('nova-cifrada', $modelo->configuracao()->chave_cifrada);
    }

    public function testRotasNegamSemPermissaoAntesDeLerCredenciais(): void
    {
        foreach (['index', 'salvar', 'executar'] as $metodo) {
            $controller = new class extends Whatsapp {
                public function __construct()
                {
                }
                protected function hasAnyPermission(array $permissoes): bool
                {
                    return false;
                }
            };
            try {
                $controller->{$metodo}();
                $this->fail('Deveria negar o acesso.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('Sem permissão', $e->getMessage());
            }
        }
    }
}
