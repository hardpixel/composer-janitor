<?php

/**
 * Composer Janitor Plugin
 * @package Hardpixel\Composer\Janitor
 */

namespace Hardpixel\Composer\Janitor;

use Composer\Composer;
use Composer\EventDispatcher\Event;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;
use Composer\Package\BasePackage;

/**
 * Class Plugin
 * @package Hardpixel\Composer\Janitor
 *
 * @SuppressWarnings(PHPMD.ShortVariable)
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
  /**
   * @var  \Composer\Composer $composer
   */
  protected $composer;

  /**
   * @var  \Composer\IO\IOInterface $io
   */
  protected $io;

  /**
   * @var  \Composer\Config $config
   */
  protected $config;

  /**
   * @var  \Composer\Util\Filesystem $filesystem
   */
  protected $filesystem;

  /**
   * @var  array $rules
   */
  protected $rules;

  /**
   * {@inheritDoc}
   */
  public function activate( Composer $composer, IOInterface $io )
  {
    $this->composer   = $composer;
    $this->io         = $io;
    $this->config     = $composer->getConfig();
    $this->filesystem = new Filesystem();
  }

  /**
   * {@inheritDoc}
   */
  public static function getSubscribedEvents()
  {
    return array(
      ScriptEvents::POST_PACKAGE_INSTALL => array(
        array( 'onPostPackageInstall', 0 ),
      ),
      ScriptEvents::POST_PACKAGE_UPDATE  => array(
        array( 'onPostPackageUpdate', 0 ),
      ),
      ScriptEvents::POST_INSTALL_CMD     => array(
        array( 'onPostInstallUpdateCmd', 0 ),
      ),
      ScriptEvents::POST_UPDATE_CMD      => array(
        array( 'onPostInstallUpdateCmd', 0 ),
      ),
    );
  }

  /**
   * Function to run after a package has been installed
   */
  public function onPostPackageInstall( Event $event )
  {
    /** @var \Composer\Package\CompletePackage $package */
    $package = $event->getOperation()->getPackage();

    $this->cleanPackage( $package );
  }

  /**
   * Function to run after a package has been updated
   */
  public function onPostPackageUpdate( Event $event )
  {
    /** @var \Composer\Package\CompletePackage $package */
    $package = $event->getOperation()->getTargetPackage();

    $this->cleanPackage( $package );
  }

  /**
   * Function to run after a package has been updated
   */
  public function onPostInstallUpdateCmd( Event $event )
  {
    /** @var \Composer\Repository\WritableRepositoryInterface $repository */
    $repository = $this->composer->getRepositoryManager()->getLocalRepository();

    /** @var \Composer\Package\CompletePackage $package */
    foreach ( $repository->getPackages() as $package ) {
      if ( $package instanceof BasePackage ) {
        $this->cleanPackage( $package );
      }
    }

    $this->cleanVendor();
  }

  /**
   * Clean a package, based on its rules.
   *
   * @param BasePackage $package The package to clean
   * @return bool True if cleaned
   *
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function cleanPackage( BasePackage $package )
  {
    $vendorDir    = $this->config->get( 'vendor-dir');
    $installDir   = $this->config->get( 'installer-paths' );
    $targetDir    = $package->getTargetDir();
    $packageName  = $package->getPrettyName();
    $packageDir   = $targetDir ? $packageName . '/' . $targetDir : $packageName;
    $packageRules = $this->getRules( $packageName );

    if ( ! $packageRules ) {
      $this->io->writeError( 'Rules not found: ' . $packageName );
      return false;
    }

    $dir = $this->filesystem->normalizePath( realpath( $vendorDir . '/' . $packageDir ) );

    if ( ! is_dir( $dir ) ) {
      $vendorDir  = $installDir;
      $packageDir = explode( '/', $packageName )[1];

      $dir = $this->filesystem->normalizePath( realpath( $vendorDir . '/' . $packageDir ) );
    }

    if ( ! is_dir( $dir ) ) {
      $this->io->writeError( 'Vendor dir not found: ' . $vendorDir . '/' . $packageDir );
      return false;
    }

    foreach ( (array) $packageRules as $part ) {
      // Split patterns for single globs (should be max 260 chars)
      $patterns = (array) $part;

      foreach ( $patterns as $pattern ) {
        try {
          foreach ( glob( $dir . '/' . $pattern ) as $file ) {
            $this->filesystem->remove($file);
          }
        } catch ( \Exception $e ) {
          $this->io->write( "Could not parse $packageDir ($pattern): " . $e->getMessage() );
        }
      }
    }

    return true;
  }

  /**
   * Clean vendor folder
   *
   * @param BasePackage $package The package to clean
   * @return bool True if cleaned
   *
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  protected function cleanVendor()
  {
    $vendorDir  = $this->config->get( 'vendor-dir' );
    $installers = $this->filesystem->normalizePath( realpath( $vendorDir . '/composer/installers' ) );
    $license    = $this->filesystem->normalizePath( realpath( $vendorDir . '/composer/LICENSE' ) );
    $binaries   = $this->filesystem->normalizePath( realpath( $vendorDir . '/bin' ) );
    $files      = array( $installers, $license, $binaries );

    foreach ( $files as $file ) {
      $this->filesystem->remove( $file );
    }

    return true;
  }

  /**
   * Rule list
   * @return array
   */
  protected function getRules( $package )
  {
    // Default patterns for common files
    $rules = array(
      'docs' => array(
        'README*',
        'readme*',
        'CHANGELOG*',
        'changelog*',
        'CHANGES*',
        'FAQ*',
        'CONTRIBUTING*',
        'HISTORY*',
        'UPGRADING*',
        'UPGRADE*',
        'CREDITS*',
        'LICENSE*',
        'license*',
        'RELEASE*',
        'COPYING*',
        'VERSION*',
        'API*',
        'INSTALL*',
        'package*',
        'demo',
        'example',
        'examples',
        'doc',
        'docs',
        'pear*',
        'phpdoc*',
        '*.md',
      ),
      'tests' => array(
        '.travis.yml',
        '.scrutinizer.yml',
        '.codeclimate.yml',
        '.coveralls.yml',

        'build.*',
        'config.*',
        'phpunit.*',
        'phpunit-*',

        'test',
        'tests',
        'Tests',
        'example',
        'examples',
        'tutorials',
        'travis',

        'demo.php',
        'test.php',
        'example.php',
        'sample.php',
      ),
      'system' => array(
        '.git*',
        '.idea',
        '.htaccess',
        '.editorconfig',
        '.phpstorm.meta.php',
        '.php_cs',
        '*.iml',
        'composer.lock',
        'bower*',

        'Makefile',
      ),
      'wp' => array(
        'composer.json',
        'composer.lock',
        'uninstall.php',
        'index.php',
        '*.pot',
        '*.dev.*',
        '*.png',
        '*.jpg',
        '*.jpeg',
        '*.gif',
        '*.txt',
      )
    );

    $cleanup          = $this->config->get( 'cleanup' );
    $cleanup_disable  = isset( $cleanup['disable'] ) ? $cleanup['disable'] : null;
    $cleanup_rules    = isset( $cleanup['rules'] ) ? $cleanup['rules'] : null;
    $cleanup_packages = isset( $cleanup['packages'] ) ? $cleanup['packages'] : null;

    if ( $cleanup_disable ) {
      $disable_rules = isset( $cleanup_disable['rules'] ) ? $cleanup_disable['rules'] : null;

      if ( $disable_rules ) {
        if ( ! is_array( $disable_rules ) )
          $disable_rules = array( $disable_rules );

        $rules = $rules - $disable_rules;
      }

      $disable_packages = isset( $cleanup_disable['packages'] ) ? $cleanup_disable['packages'] : null;
      if ( $disable_packages and in_array( $package, $disable_packages ) ) return;
    }

    if ( $cleanup_rules ) {
      if ( ! is_array( $cleanup_rules ) )
        $cleanup_rules = array( $cleanup_rules );

      $rules = $rules + $cleanup_rules;
    }

    if ( $cleanup_packages ) {
      $package_rules = isset( $cleanup_packages[$package] ) ? $cleanup_packages[$package] : array();

      if ( ! is_array( $package_rules ) )
        $package_rules = array( $package_rules );

      if ( ! empty( $package_rules ) )
        $rules[] = $package_rules;
    }

    if ( stripos( $package, 'wpackagist-plugin' ) === false )
      unset( $rules['wp'] );

    return $rules;
  }
}
