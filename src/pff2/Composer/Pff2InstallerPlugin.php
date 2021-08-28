<?php

namespace pff2\Composer;
use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

class Pff2InstallerPlugin implements PluginInterface
{
    public function activate(Composer $composer, IOInterface $io)
    {
        $installer = new Pff2Installer($io, $composer);
        $composer->getInstallationManager()->addInstaller($installer);
    }

    public function uninstall(Composer $composer, IOInterface $io) {
        return;
    }
    
    public function deactivate(Composer $composer, IOInterface $io) {
        return;
    }
}
