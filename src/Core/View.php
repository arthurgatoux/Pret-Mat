<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class View
{
    private static string $viewsPath = __DIR__ . '/../../views';

    /**
     * @param array<string, mixed> $data
     */
    public static function render(string $template, array $data = [], bool $withLayout = true): string
    {
        $templateFile = self::$viewsPath . '/' . ltrim($template, '/') . '.php';

        if (!file_exists($templateFile)) {
            throw new RuntimeException(sprintf('Template introuvable : %s', $templateFile));
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $templateFile;
        $content = (string) ob_get_clean();

        if ($withLayout) {
            $headerFile = self::$viewsPath . '/layout/header.php';
            $footerFile = self::$viewsPath . '/layout/footer.php';

            ob_start();
            require $headerFile;
            echo $content;
            require $footerFile;
            return (string) ob_get_clean();
        }

        return $content;
    }
}
