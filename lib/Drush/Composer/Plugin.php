<?php

/**
 * @file
 * Contains Drush\Composer\Plugin.
 */

namespace Drush\Command;

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\PackageEvent;
use Composer\Script\CommandEvent;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;

/**
 * Class Plugin.
 */
class Plugin implements PluginInterface, EventSubscriberInterface {

  /**
   * @var \Composer\IO\IOInterface
   */
  protected $io;

  /**
   * @var \Composer\Composer
   */
  protected $composer;

  /**
   * @var \Composer\Util\Filesystem
   */
  protected $filesystem;

  protected $cache;

  /**
   * {@inheritdoc}
   */
  public function activate(Composer $composer, IOInterface $io) {
    $this->io = $io;
    $this->composer = $composer;
    $this->filesystem = new Filesystem();

    $cache = array();
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return array(
      // ScriptEvents::PRE_PACKAGE_INSTALL => 'prePackage',
      ScriptEvents::POST_PACKAGE_INSTALL => 'postPackageInstall',
      ScriptEvents::POST_INSTALL_CMD => 'installCompleted',
      // ScriptEvents::PRE_PACKAGE_UPDATE => 'prePackage',
      // ScriptEvents::POST_PACKAGE_UPDATE => 'postPackage',
      // ScriptEvents::PRE_PACKAGE_UNINSTALL => 'prePackage',
      // ScriptEvents::POST_PACKAGE_UNINSTALL => 'postPackage',
    );
  }

  /**
   * Pre Package event behaviour for Drush installation observer.
   *
   * @param \Composer\Script\PackageEvent $event
   */
  public function prePackage(PackageEvent $event) {
    $this->io->writeError(" - In " . __METHOD__);
  }

  /**
   * Post Package event behaviour for Drush installation observer.
   *
   * @param \Composer\Script\PackageEvent $event
   */
  public function postPackageInstall(PackageEvent $event) {
    $this->io->writeError(" - In " . __METHOD__);
    $package = $operation->getPackage();

    // Check to see if this is a type that might contain Drush extensions.
    if (($package->getType() == 'drupal-drush') || ($package->getType() == 'drupal-module')) {
      /** @var \Composer\Installer\InstallationManager $installationManager */
      $installationManager = $this->composer->getInstallationManager();

      // Find out where the component was installed
      $installPath = $installationManager->getInstallPath($package);
      $installPath = $this->absolutePath($installPath);

      // Find commandfiles in the install location
      $finder = Finder::create()->files()->name('*.drush*.inc')->in($installPath);
      $iterator = $finder->getIterator();

      foreach ($iterator as $key => $value) {
        $this->cache[$key] = $value;
      }
    }
  }

  // update events:
    //    $operation->getInitialPackage(),
    //    $operation->getTargetPackage(),

  public function installCompleted(CommandEvent $event) {
    $this->io->writeError(" - In " . __METHOD__);
    $this->io->writeError(var_export($this->cache, TRUE));
    // Write out our cached commandfile set
    file_put_contents("/tmp/composer.txt", var_export($this->cache, TRUE));
  }

  /**
   * Helper to convert a relative path to an absolute one.
   *
   * @param string $path
   * @return string
   */
  protected function absolutePath($path) {
    if (!$this->filesystem->isAbsolutePath($path)) {
      return getcwd() . '/' . $path;
    }
    return $path;
  }
}
