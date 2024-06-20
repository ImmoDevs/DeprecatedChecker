<?php

namespace DeprecatedChecker;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

class Main extends PluginBase
{
    private Config $deprecatedConfig;

    public function onEnable(): void
    {
        @mkdir($this->getDataFolder());
        $this->deprecatedConfig = new Config(
            $this->getDataFolder() . "deprecated.yml",
            Config::YAML
        );

        set_error_handler([$this, "handleDeprecationWarnings"], E_DEPRECATED);
    }

    public function handleDeprecationWarnings(
        int $errno,
        string $errstr,
        string $errfile,
        int $errline
    ): void {
        if ($errno === E_DEPRECATED) {
            $pluginName = $this->extractPluginName($errfile);

            if ($pluginName === null) {
                $pluginName = "UnknownPlugin";
            }

            $existingEntries = $this->deprecatedConfig->getAll();
            if (isset($existingEntries[$pluginName])) {
                foreach ($existingEntries[$pluginName] as $entry) {
                    if (
                        $entry["message"] === $errstr &&
                        $entry["file"] === $errfile &&
                        $entry["line"] === $errline
                    ) {
                        return;
                    }
                }
            }

            $entry = [
                "message" => $errstr,
                "file" => $errfile,
                "line" => $errline,
            ];

            if (!isset($existingEntries[$pluginName])) {
                $existingEntries[$pluginName] = [];
            }

            $existingEntries[$pluginName][] = $entry;
            $this->deprecatedConfig->setAll($existingEntries);
            $this->deprecatedConfig->save();

            $this->getLogger()->info("§o§aDeprecated warning detected and saved.");
        }
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
}
