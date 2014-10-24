<?php

namespace Composer\Installer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;

/**
 * Pff2 package installer for Composer
 *
 * @package pff2-installers
 * @author  Paolo Fagni <paolo.fagni@gmail.com>
 * @license MIT license
 * @link    https://github.com/stonedz/pff2-installers
 */
class Pff2Installer extends LibraryInstaller {

	protected $package_install_paths = array(
		'pff2-module' 	 	=> 'modules/{name}/',
        'pff2-core'         => 'vendor/{vendor}/{name}'
	);

	/**
	 * {@inheritDoc}
	 */
	public function supports($packageType){
		return array_key_exists($packageType, $this->package_install_paths);
	}

	/**
	 * {@inheritDoc}
	 */
	public function getInstallPath(PackageInterface $package){
		$type = $package->getType();
		
		if (!isset($this->package_install_paths[$type]))
		{
			throw new \InvalidArgumentException("Package type '$type' is not supported at this time.");
		}

		$prettyName = $package->getPrettyName();
		if (strpos($prettyName, '/') !== false) {
			list($vendor, $name) = explode('/', $prettyName);
		} else {
			$vendor = '';
			$name = $prettyName;
		}
		
		$extra = ($this->composer->getPackage())
		       ? $this->composer->getPackage()->getExtra()
		       : array();
		
		$appdir = (!empty($extra['pff2-application-dir']))
		        ? $extra['pff2-application-dir']
		        : 'app';

		$vars = array(
			'{name}'        => $name,
			'{vendor}'      => $vendor,
			'{type}'        => $type,
			'{application}' => $appdir,
		);
		
		return str_replace(array_keys($vars), array_values($vars), $this->package_install_paths[$type]);
	}
	
	/**
	 * {@inheritDoc}
	 */
	protected function installCode(PackageInterface $package){
		$downloadPath = $this->getInstallPath($package);
		$this->downloadManager->download($package, $downloadPath);
		$this->postInstallActions($package->getType(), $downloadPath, $package);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function updateCode(PackageInterface $initial, PackageInterface $target){
		$downloadPath = $this->getInstallPath($initial);
		$this->downloadManager->update($initial, $target, $downloadPath);
		$this->postUpdateActions($target->getType(), $downloadPath, $target);
	}
	
	/**
	 * Performs actions on the downloaded files after an installation or update
	 * 
	 * @var string $type
	 * @var string $downloadPath
	 */
	protected function postInstallActions($type, $downloadPath, PackageInterface $package){
		switch ($type)
		{
            case 'pff2-module':
                $this->moveConfiguration($downloadPath, $package);
                $this->updatePff();
                break;
            case 'pff2-core':
                $this->initPff();
                $this->updatePff();
                break;
		}
	}

    /**
     * Performs actions on the updated files after an installation or update
     *
     * @var string $type
     * @var string $downloadPath
     */
    protected function postUpdateActions($type, $downloadPath, PackageInterface $package){
        switch ($type)
        {
            case 'pff2-module':
                $this->moveConfiguration($downloadPath, $package);
                $this->updatePff();
                break;
            case 'pff2-core':
                $this->updatePff();
                break;
        }
    }

    protected function moveConfiguration($downloadPath, PackageInterface $package) {
        $dst = realpath($downloadPath);
        if( file_exists($dst.'/module.conf.yaml') && !file_exists($dst.'/module.conf.local.yaml')) {
            copy($dst.'/module.conf.yaml', $dst.'/module.conf.local.yaml');
        }
        else {
            echo 'Configuration file for module '. $package->getPrettyName(). 'has NOT be copied to local configuration file, please check for any changes manually';
        }
    }


	/**
	 * Move files out of the package directory up one level
	 *
	 * @var $downloadPath
	 * @var $wildcard = '*.php'
	 */
	protected function moveCoreFiles($downloadPath, $wildcard = '*.php'){
		$dst = realpath($downloadPath);
	}

    protected function updatePff() {
        shell_exec('vendor/bin/generateAutoload.sh');
    }

    protected function initPff() {
        shell_exec('vendor/stonedz/pff2/scripts/init');
    }
}
