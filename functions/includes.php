<?php
function require_all_php(string $baseDir, array $exclude = [], array $ignoreFiles = []): void {
    $baseDir = realpath($baseDir);
    if ($baseDir === false || !is_dir($baseDir)) return;

    $dir = new RecursiveDirectoryIterator(
        $baseDir,
        FilesystemIterator::SKIP_DOTS // não seguir symlinks para evitar loops
    );

    $filter = new RecursiveCallbackFilterIterator($dir, function (SplFileInfo $current, $key, $iterator) use ($exclude) {
        $path = $current->getPathname();

        // Pula diretórios/arquivos que batem com termos de exclusão
        foreach ($exclude as $ex) {
            $ex = trim($ex, "\\/");

            // Se for diretório, compare por segments (basename) e também por substring com separadores
            if ($current->isDir()) {
                if (strcasecmp($current->getBasename(), $ex) === 0) {
                    return false; // não desce neste diretório
                }
            }
            if (stripos($path, DIRECTORY_SEPARATOR . $ex . DIRECTORY_SEPARATOR) !== false
                || str_ends_with(strtolower($path), DIRECTORY_SEPARATOR . strtolower($ex))) {
                return false;
            }
        }
        // Evita seguir symlinks
        if ($current->isLink()) return false;

        return true;
    });

    $it = new RecursiveIteratorIterator($filter, RecursiveIteratorIterator::SELF_FIRST);

    // Coleta e ordena para previsibilidade
    $files = [];
    foreach ($it as $file) {
        if ($file->isFile() && str_ends_with(strtolower($file->getFilename()), '.php')) {
            $files[] = $file->getPathname();
        }
    }
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);

    // Ignora arquivos específicos (ex.: este bootstrap)
    $ignoreMap = array_flip(array_map('realpath', $ignoreFiles));

    foreach ($files as $phpFile) {
        $rp = realpath($phpFile);
        if ($rp !== false && !isset($ignoreMap[$rp])) {
            require_once $rp;
        }
    }
}


$base = realpath(__DIR__ . '/..');
require_all_php(
    $base,
    ['vendor', 'node_modules', 'cache', 'storage', 'components', 'templates'], // pastas a ignorar
    [__FILE__, __DIR__ . '/../museu-franca.php', __DIR__ . '/functions.php'] // ignora este arquivo
);
