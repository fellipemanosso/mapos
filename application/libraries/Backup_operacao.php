<?php

/** Cópia diária do banco e anexos privados. O destino obrigatoriamente fica fora da raiz web. */
class Backup_operacao
{
    public function destino(string $destino, string $raiz): string
    {
        if (! armazenamentoCaminhoAbsoluto($destino) || ! is_dir($destino)) {
            throw new RuntimeException('Crie BACKUP_PATH como diretório absoluto fora da raiz web.');
        }
        $real = realpath($destino);
        $web = realpath($raiz);
        if (! $real || ! $web || str_starts_with(strtolower(str_replace('\\', '/', $real)) . '/', strtolower(str_replace('\\', '/', $web)) . '/')) {
            throw new RuntimeException('O backup não pode ficar dentro da raiz web.');
        }
        $storage = realpath(armazenamentoRaiz());
        if ($storage && str_starts_with(strtolower(str_replace('\\', '/', $real)) . '/', strtolower(str_replace('\\', '/', $storage)) . '/')) {
            throw new RuntimeException('O backup deve ficar fora do armazenamento que será copiado.');
        }
        if (! is_writable($real)) {
            throw new RuntimeException('O diretório de backup não permite gravação.');
        }
        return $real;
    }

    public function executar(): string
    {
        $ci = &get_instance();
        $pasta = $this->destino((string) ($_ENV['BACKUP_PATH'] ?? ''), FCPATH);
        $lock = fopen($pasta . '/mapos-backup.lock', 'c');
        if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Já existe um backup em execução.');
        }
        $temporario = null;
        try {
            $hoje = $pasta . '/mapos-operacao-' . date('Y-m-d') . '.zip';
            if (is_file($hoje)) {
                return basename($hoje);
            }
            $temporario = tempnam($pasta, 'mapos-tmp-');
            if (! $temporario) {
                throw new RuntimeException('Não foi possível preparar o backup.');
            }
            chmod($temporario, 0600);
            $ci->load->dbutil();
            $sql = $ci->dbutil->backup(['format' => 'txt', 'foreign_key_checks' => false,
                'ignore' => ['ci_sessions', 'login_attempts', 'api_refresh_tokens', 'api_rate_limit', 'resets_de_senha']]);
            if (! is_string($sql) || $sql === '') {
                throw new RuntimeException('O banco não gerou um backup válido.');
            }
            $zip = new ZipArchive();
            if ($zip->open($temporario, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Não foi possível abrir o arquivo de backup.');
            }
            $zip->addFromString('banco.sql', $sql);
            $zip->addFromString('manifesto.json', json_encode(['formato' => 1, 'criado_em' => date(DATE_ATOM),
                'aviso' => 'Contém dados privados. A chave APP_ENCRYPTION_KEY e o .env são guardados separadamente.']));
            $storage = armazenamentoRaiz();
            if (is_dir($storage)) {
                $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($storage, FilesystemIterator::SKIP_DOTS));
                foreach ($it as $arquivo) {
                    if ($arquivo->isFile() && ! $arquivo->isLink() && ! str_ends_with($arquivo->getFilename(), '.lock')) {
                        $relativo = substr($arquivo->getPathname(), strlen($storage) + 1);
                        $zip->addFile($arquivo->getPathname(), 'storage/' . str_replace('\\', '/', $relativo));
                    }
                }
            }
            foreach (['assets/uploads', 'assets/userImage'] as $publica) {
                $diretorio = rtrim(FCPATH, '/\\') . '/' . $publica;
                if (! is_dir($diretorio)) {
                    continue;
                }
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($diretorio, FilesystemIterator::SKIP_DOTS)) as $arquivo) {
                    if ($arquivo->isFile() && ! $arquivo->isLink()) {
                        $zip->addFile($arquivo->getPathname(), 'public/' . $publica . '/' . str_replace('\\', '/', substr($arquivo->getPathname(), strlen($diretorio) + 1)));
                    }
                }
            }
            if (! $zip->close() || ! rename($temporario, $hoje)) {
                throw new RuntimeException('Não foi possível concluir o backup.');
            }
            $temporario = null;
            chmod($hoje, 0600);
            $dias = max(7, min(365, (int) ($_ENV['BACKUP_RETENTION_DAYS'] ?? 30)));
            foreach (glob($pasta . '/mapos-operacao-????-??-??.zip') ?: [] as $arquivo) {
                if (is_file($arquivo) && ! is_link($arquivo) && filemtime($arquivo) < time() - $dias * 86400) {
                    unlink($arquivo);
                }
            }
            return basename($hoje);
        } finally {
            if ($temporario && is_file($temporario)) {
                unlink($temporario);
            }
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
