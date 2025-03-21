<?php

namespace DeprecatedChecker;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\Server;

class Main extends PluginBase
{
    private array $deprecatedFiles = [];
    private array $warningHashes = [];
    private string $dataPath;
    private Config $warningIndex;

    public function onEnable(): void
    {
        $this->dataPath = $this->getDataFolder();
        @mkdir($this->dataPath);
        @mkdir($this->dataPath . "logs/");

        // Load warning index
        $this->warningIndex = new Config($this->dataPath . "warning_index.json", Config::JSON);
        $this->warningHashes = $this->warningIndex->getAll();

        // Set error handler for deprecated warnings
        set_error_handler([$this, "handleDeprecationWarnings"], E_DEPRECATED | E_USER_DEPRECATED);

        $this->getLogger()->info(TextFormat::GREEN . "DeprecatedChecker has been enabled!");
    }

    private function getPluginFromFilePath(string $filePath): string
    {
        foreach (Server::getInstance()->getPluginManager()->getPlugins() as $plugin) {
            if (strpos($filePath, $plugin->getFile()) !== false) {
                return $plugin->getName();
            }
        }
        
        return $this->extractPluginName($filePath) ?? "Unknown-Plugin";
    }

    private function extractPluginName(string $filePath): ?string
    {
        $parts = explode(DIRECTORY_SEPARATOR, $filePath);
        $pluginsIndex = array_search('plugins', $parts);

        if ($pluginsIndex !== false && isset($parts[$pluginsIndex + 1])) {
            return $parts[$pluginsIndex + 1];
        }

        return null;
    }

    private function createLogFileForPlugin(string $pluginName): string
    {
        $logFilePath = $this->dataPath . "logs/" . $this->sanitizeFileName($pluginName) . ".log";
        if (!file_exists($logFilePath)) {
            file_put_contents($logFilePath, "# Deprecation logs for {$pluginName}\n# Format: [Timestamp] Message (File:Line)\n\n");
        }
        return $logFilePath;
    }

    private function sanitizeFileName(string $filename): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '_', $filename);
    }

    public function handleDeprecationWarnings(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        if (!(error_reporting() & $errno)) {
            return false;
        }

        if ($errno === E_DEPRECATED || $errno === E_USER_DEPRECATED) {
            $pluginName = $this->getPluginFromFilePath($errfile);
            $warningHash = md5($errstr . '|' . $errfile . '|' . $errline);

            if (isset($this->warningHashes[$pluginName]) && in_array($warningHash, $this->warningHashes[$pluginName])) {
                return false;
            }

            $logFile = $this->createLogFileForPlugin($pluginName);
            $this->warningHashes[$pluginName][] = $warningHash;
            $this->warningIndex->set($pluginName, $this->warningHashes[$pluginName]);
            $this->warningIndex->save();

            $logEntry = "[" . date('Y-m-d H:i:s') . "] {$errstr} ({$errfile}:{$errline})\n";
            file_put_contents($logFile, $logEntry, FILE_APPEND);

            $this->getLogger()->info(TextFormat::YELLOW . "Deprecation warning in " .
                TextFormat::AQUA . $pluginName . TextFormat::RESET .
                ": " . $errstr . " at " . basename($errfile) . ":{$errline} " .
                TextFormat::GREEN . "(saved)");
        }

        return false;
    }

    public function onDisable(): void
    {
        restore_error_handler();
        $this->warningIndex->save();
    }
}
