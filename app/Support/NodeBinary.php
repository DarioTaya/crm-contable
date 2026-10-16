<?php

namespace App\Support;

class NodeBinary
{
    /**
     * Resuelve la ruta al ejecutable de Node.js en Windows o entornos Linux/macOS.
     */
    public static function path(): string
    {
        $laragonNodes = glob(base_path('../../bin/nodejs/*/node.exe')) ?: glob('C:/laragon/bin/nodejs/*/node.exe');
        if (! empty($laragonNodes) && file_exists($laragonNodes[0])) {
            return str_replace('/', '\\', $laragonNodes[0]);
        }

        $possiblePaths = [
            'C:\\laragon\\bin\\nodejs\\node-v22\\node.exe',
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe',
            (getenv('LOCALAPPDATA') ?: '').'\\Programs\\nodejs\\node.exe',
        ];

        foreach ($possiblePaths as $path) {
            if ($path && file_exists($path)) {
                return $path;
            }
        }

        return 'node';
    }
}
