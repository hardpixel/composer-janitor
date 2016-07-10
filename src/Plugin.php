<?php

/**
 * Jonian Composer Cleanup Plugin
 * @package Jonian\Composer\Cleanup
 */

namespace Jonian\Composer\Cleanup;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\ScriptEvents;
use Composer\Script\PackageEvent;
use Composer\Script\CommandEvent;
use Composer\Util\Filesystem;
use Composer\Package\BasePackage;

/**
 * Class Plugin
 * @package Jonian\Composer\Cleanup
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
	public function activate(Composer $composer, IOInterface $io)
	{
		$this->composer   = $composer;
		$this->io         = $io;
		$this->config     = $composer->getConfig();
		$this->filesystem = new Filesystem();
		$this->rules      = $this->getRules();
	}

	/**
	 * {@inheritDoc}
	 */
	public static function getSubscribedEvents()
	{
		return array(
			ScriptEvents::POST_PACKAGE_INSTALL => array(
				array('onPostPackageInstall', 0),
			),
			ScriptEvents::POST_PACKAGE_UPDATE  => array(
				array('onPostPackageUpdate', 0),
			),
			ScriptEvents::POST_INSTALL_CMD     => array(
				array('onPostInstallUpdateCmd', 0),
			),
			ScriptEvents::POST_UPDATE_CMD      => array(
				array('onPostInstallUpdateCmd', 0),
			),
		);
	}

	/**
	 * Function to run after a package has been installed
	 */
	public function onPostPackageInstall(PackageEvent $event)
	{
		/** @var \Composer\Package\CompletePackage $package */
		$package = $event->getOperation()->getPackage();

		$this->cleanPackage($package);
	}

	/**
	 * Function to run after a package has been updated
	 */
	public function onPostPackageUpdate(PackageEvent $event)
	{
		/** @var \Composer\Package\CompletePackage $package */
		$package = $event->getOperation()->getTargetPackage();

		$this->cleanPackage($package);
	}

	/**
	 * Function to run after a package has been updated
	 *
	 * @param CommandEvent $event
	 */
	public function onPostInstallUpdateCmd(CommandEvent $event)
	{
		/** @var \Composer\Repository\WritableRepositoryInterface $repository */
		$repository = $this->composer->getRepositoryManager()->getLocalRepository();

		/** @var \Composer\Package\CompletePackage $package */
		foreach ($repository->getPackages() as $package) {
			if ($package instanceof BasePackage) {
				$this->cleanPackage($package);
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
	protected function cleanPackage(BasePackage $package)
	{
		$vendorDir   = $this->config->get('vendor-dir');
		$installDir  = $this->config->get('installer-paths');
		$targetDir   = $package->getTargetDir();
		$packageName = $package->getPrettyName();
		$packageDir  = $targetDir ? $packageName . '/' . $targetDir : $packageName;

		$rules = isset($this->rules[$packageName]) ? $this->rules[$packageName] : null;

		if (!$rules) {
			$this->io->writeError('Rules not found: ' . $packageName);
			return false;
		}

		$dir = $this->filesystem->normalizePath(realpath($vendorDir . '/' . $packageDir));

		if (!is_dir($dir)) {
			$vendorDir  = $installDir;
			$packageDir = explode('/', $packageName)[1];

			$dir = $this->filesystem->normalizePath(realpath($vendorDir . '/' . $packageDir));
		}

		if (!is_dir($dir)) {
			$this->io->writeError('Vendor dir not found: ' . $vendorDir . '/' . $packageDir);
			return false;
		}

		foreach ((array)$rules as $part) {
			// Split patterns for single globs (should be max 260 chars)
			$patterns = (array)$part;

			foreach ($patterns as $pattern) {
				try {
					foreach (glob($dir . '/' . $pattern) as $file) {
						$this->filesystem->remove($file);
					}
				} catch (\Exception $e) {
					$this->io->write("Could not parse $packageDir ($pattern): " . $e->getMessage());
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
		$vendorDir = $this->config->get('vendor-dir');
		$installers = $this->filesystem->normalizePath(realpath($vendorDir . '/composer/installers'));
		$license = $this->filesystem->normalizePath(realpath($vendorDir . '/composer/LICENSE'));
		$binaries = $this->filesystem->normalizePath(realpath($vendorDir . '/bin'));
		$files = array( $installers, $license, $binaries );

		foreach ( $files as $file ) {
			$this->filesystem->remove($file);
		}

		return true;
	}

	/**
	 * Rule list
	 * @return array
	 */
	public static function getRules()
	{
		// Default patterns for common files
		$docs = array(
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
		);

		$tests = array(
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
		);

		$system = array(
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
		);

		$wp = array(
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
		);

		return array(
			// Core
			'composer/installers'               => array( $docs, $tests, $system, array( 'installers' ) ),

			// Libraries
			'jonian/composer-cleanup'           => array( $docs, $tests, $system ),
			'aura/autoload'                     => array( $docs, $tests, $system ),
			'bensquire/php-image-optim'         => array( $docs, $tests, $system ),
			'filp/whoops'                       => array( $docs, $tests, $system ),
			'masterminds/html5'                 => array( $docs, $tests, $system, array( 'sami.php', 'bin' ) ),
			'querypath/querypath'               => array( $docs, $tests, $system, array( 'patches', 'bin', 'phar' ) ),
			'predis/predis'                     => array( $docs, $tests, $system ),
			'danielstjules/stringy'             => array( $docs, $tests, $system ),
			'mikehaertl/tmpfile'                => array( $docs, $tests, $system ),
			'mikehaertl/phpwkhtmltopdf'         => array( $docs, $tests, $system ),
			'mikehaertl/php-shellcommand'       => array( $docs, $tests, $system ),
			'mjphaynes/php-resque'              => array( $docs, $tests, $system ),
			'erusev/parsedown'                  => array( $docs, $tests, $system ),
			'hashids/hashids'                   => array( $docs, $tests, $system ),
			'html2text/html2text'               => array( $docs, $tests, $system ),
			'league/csv'                        => array( $docs, $tests, $system ),
			'lusitanian/oauth'                  => array( $docs, $tests, $system ),
			'mailchimp/mailchimp'               => array( $docs, $tests, $system ),
			'matthiasmullie/minify'             => array( $docs, $tests, $system, array( 'bin' ) ),
			'matthiasmullie/path-converter'     => array( $docs, $tests, $system ),
			'mike182uk/cart'                    => array( $docs, $tests, $system ),
			'mikehaertl/php-tmpfile'            => array( $docs, $tests, $system ),
			'misd/linkify'                      => array( $docs, $tests, $system ),
			'money/money'                       => array( $docs, $tests, $system ),
			'monolog/monolog'                   => array( $docs, $tests, $system ),
			'nojacko/email-validator'           => array( $docs, $tests, $system ),
			'nojacko/email-data-disposable'     => array( $docs, $tests, $system ),
			'pelago/emogrifier'                 => array( $docs, $tests, $system, array( 'Configuration' ) ),
			'psr/log'                           => array( $docs, $tests, $system ),
			'seostats/seostats'                 => array( $docs, $tests, $system ),
			'symfony/console'                   => array( $docs, $tests, $system ),
			'symfony/polyfill-mbstring'         => array( $docs, $tests, $system ),
			'symfony/process'                   => array( $docs, $tests, $system ),
			'symfony/yaml'                      => array( $docs, $tests, $system ),
			'vegeta/fluxer'                     => array( $docs, $tests, $system ),
			'zaininnari/html-minifier'          => array( $docs, $tests, $system ),

			// Plugins
			'humanmade/mercator'                     => array( $docs, $tests, $system, $wp ),
			'wcm/wp-importer'                        => array( $docs, $tests, $system, $wp ),
			'roots/wp-password-bcrypt'               => array( $docs, $tests, $system, $wp ),
			'wpackagist-plugin/blogger-importer'     => array( $docs, $tests, $system, $wp ),
			'wpackagist-plugin/duplicate-post'       => array( $docs, $tests, $system, $wp ),
			'wpackagist-plugin/livejournal-importer' => array( $docs, $tests, $system, $wp ),
			'wpackagist-plugin/movabletype-importer' => array( $docs, $tests, $system, $wp ),
			'wpackagist-plugin/opml-importer'        => array( $docs, $tests, $system, $wp ),
			'wpackagist-plugin/piklist'              => array( $docs, $tests, $system, $wp, array( 'add-ons' ) ),
			'wpackagist-plugin/playbuzz'             => array( $docs, $tests, $system, $wp ),
			'wpackagist-plugin/wordpress-seo'        => array( $docs, $tests, $system, $wp ),
			'wpackagist-plugin/json-rest-api'        => array( $docs, $tests, $system, $wp ),
			'wpackagist-plugin/wp-search-live'       => array( $docs, $tests, $system, $wp ),
			'wpackagist-plugin/polylang'             => array( $docs, $tests, $system, $wp, array( 'lingotek' ) ),
			'wpackagist-plugin/rss-importer'         => array( $docs, $tests, $system, $wp ),
			'wpackagist-plugin/simple-page-ordering' => array( $docs, $tests, $system, $wp ),
			'wpackagist-plugin/tumblr-importer'      => array( $docs, $tests, $system, $wp ),
			'wpackagist-plugin/wpcat2tag-importer'   => array( $docs, $tests, $system, $wp ),
		);
	}
}
