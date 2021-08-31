<?php

namespace pff2\Composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;
use React\Promise\PromiseInterface;

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
		'pff2-module' 	 	=> 'modules/{name}',
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
    public function getInstallPath(PackageInterface $package):string 
    {

		$type = $package->getType();

		$prettyName = $package->getPrettyName();
		if (strpos($prettyName, '/') !== false) {
			list($vendor, $name) = explode('/', $prettyName);
		} else {
			$vendor = '';
			$name = $prettyName;
		}
		
		$vars = array(
			'{name}'        => $name,
		);

        return  str_replace(array_keys($vars), array_values($vars), $this->package_install_paths[$type]);
	}

	
	/**
	 * {@inheritDoc}
     *
	 */
    protected function installCode(PackageInterface $package){
        $downloadPath = $this->getInstallPath($package);
        $promise = $this->downloadManager->install($package, $downloadPath);

        return $promise->then(function () use ($downloadPath, $package){
            $this->postUpdateActions($package->getType(), $downloadPath, $package);
        });    
    }
    

	/**
	 * {@inheritDoc}
	 */
    protected function updateCode(PackageInterface $initial, PackageInterface $target){
        $initialDownloadPath = $this->getInstallPath($initial);
        $targetDownloadPath = $this->getInstallPath($target);
        if ($targetDownloadPath !== $initialDownloadPath) {
            // if the target and initial dirs intersect, we force a remove + install
            // to avoid the rename wiping the target dir as part of the initial dir cleanup
            if (strpos($initialDownloadPath, $targetDownloadPath) === 0
                || strpos($targetDownloadPath, $initialDownloadPath) === 0
            ) {
                $promise = $this->removeCode($initial);
                if (!$promise instanceof PromiseInterface) {
                    $promise = \React\Promise\resolve();
                }

                $self = $this;

                return $promise->then(function () use ($self, $target) {
                    $reflMethod = new \ReflectionMethod($self, 'installCode');
                    $reflMethod->setAccessible(true);

                    // equivalent of $this->installCode($target) with php 5.3 support
                    // TODO remove this once 5.3 support is dropped
                    return $reflMethod->invoke($self, $target);
                });
            }

            $this->filesystem->rename($initialDownloadPath, $targetDownloadPath);
        }
        $promise = $this->downloadManager->update($initial, $target, $targetDownloadPath);
        return $promise->then( function () use ($targetDownloadPath, $target){
            $this->postUpdateActions($target->getType(), $targetDownloadPath, $target);
        });
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
        }
    }

    protected function moveConfiguration($downloadPath, PackageInterface $package) {
        $prettyName = $package->getPrettyName();
        if (strpos($prettyName, '/') !== false) {
            list($vendor, $name) = explode('/', $prettyName);
        } else {
            $vendor = '';
            $name = $prettyName;
        }
        $dst = realpath($downloadPath);
        if( file_exists($dst.'/module.conf.yaml') 
            && !file_exists($dst.'/../../app/config/modules/'.$name.'/module.conf.local.yaml')) 
        {
            if(!file_exists($dst.'/../../app/config/modules/'.$name)
            ) {
                mkdir($dst.'/../../app/config/modules/'.$name,0755,true);
            }
            copy($dst.'/module.conf.yaml', $dst.'/../../app/config/modules/'.$name.'/module.conf.local.yaml');
        }
        else {
            echo 'Configuration file for module '. $package->getPrettyName(). ' has NOT be copied to local configuration file, please check for any changes manually';
        }
    }

    protected function updatePff() {
        return true;
    }
    protected function initPff() {
        shell_exec('vendor/stonedz/pff2/scripts/init');
    }
}
