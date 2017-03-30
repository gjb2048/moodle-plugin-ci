<?php

/*
 * This file is part of the Moodle Plugin CI package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Copyright (c) 2017 Blackboard Inc. (http://www.blackboard.com)
 * License http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace Moodlerooms\MoodlePluginCI\Installer;

use Moodlerooms\MoodlePluginCI\Bridge\Moodle;
use Moodlerooms\MoodlePluginCI\Bridge\MoodleConfig;
use Moodlerooms\MoodlePluginCI\Installer\Database\AbstractDatabase;
use Moodlerooms\MoodlePluginCI\Process\Execute;
use Moodlerooms\MoodlePluginCI\Validate;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Installer.
 *
 * @copyright Copyright (c) 2015 Moodlerooms Inc. (http://www.moodlerooms.com)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class MoodleInstaller extends AbstractInstaller
{
    /**
     * @var Execute
     */
    private $execute;

    /**
     * @var AbstractDatabase
     */
    private $database;

    /**
     * @var Moodle
     */
    private $moodle;

    /**
     * @var MoodleConfig
     */
    private $config;

    /**
     * @var string
     */
    private $repo;

    /**
     * @var string
     */
    private $branch;

    /**
     * @var string
     */
    private $dataDir;

    /**
     * @param Execute          $execute
     * @param AbstractDatabase $database
     * @param Moodle           $moodle
     * @param MoodleConfig     $config
     * @param string           $repo
     * @param string           $branch
     * @param string           $dataDir
     */
    public function __construct(Execute $execute, AbstractDatabase $database, Moodle $moodle, MoodleConfig $config, $repo, $branch, $dataDir)
    {
        $this->execute  = $execute;
        $this->database = $database;
        $this->moodle   = $moodle;
        $this->config   = $config;
        $this->repo     = $repo;
        $this->branch   = $branch;
        $this->dataDir  = $dataDir;
    }

    public function install()
    {
        $this->getOutput()->step('Cloning Moodle');

        $process = new Process(sprintf('git clone --depth=1 --branch %s %s %s', $this->branch, $this->repo, $this->moodle->directory));
        $process->setTimeout(null);
        $this->execute->mustRun($process);

        // Expand the path to Moodle so all other installers use absolute path.
        $this->moodle->directory = $this->expandPath($this->moodle->directory);

        $this->getOutput()->step('Moodle assets');

        $this->getOutput()->debug('Creating Moodle data directories');
        $filesystem = new Filesystem();
        $filesystem->mkdir($this->dataDir);
        $filesystem->mkdir($this->dataDir.'/phpu_moodledata');
        $filesystem->mkdir($this->dataDir.'/behat_moodledata');

        $this->getOutput()->debug('Create Moodle database');
        $this->execute->mustRun($this->database->getCreateDatabaseCommand());

        $this->getOutput()->debug('Creating Moodle\'s config file');
        $contents = $this->config->createContents($this->database, $this->expandPath($this->dataDir));
        $this->config->dump($this->moodle->directory.'/config.php', $contents);

        $this->addEnv('MOODLE_DIR', $this->moodle->directory);

        // If PHP 5.6, add an INI file to disable a setting that causes a deprecation notice.
        if (PHP_MAJOR_VERSION === 5 && PHP_MINOR_VERSION === 6) {
            $this->execute->mustRun(sprintf('phpenv config-add %s', realpath(__DIR__.'/../../res/template/moodle.ini')));
        }
    }

    /**
     * Converts a path to an absolute path.
     *
     * @param string $path
     *
     * @return string
     */
    public function expandPath($path)
    {
        $validate = new Validate();

        return realpath($validate->directory($path));
    }

    public function stepCount()
    {
        return 2;
    }
}
