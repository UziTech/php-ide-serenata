<?php

namespace PhpIntegrator\Application;

use ArrayAccess;
use LogicException;
use UnexpectedValueException;

use Doctrine\Common\Cache\FilesystemCache;

use GetOptionKit\OptionParser;
use GetOptionKit\OptionCollection;

use PhpIntegrator\CachingIndexDataAdapter;

use PhpIntegrator\Indexing\IndexDatabase;

/**
 * Base class for commands.
 */
abstract class Command implements CommandInterface
{
    /**
     * The version of the database we're currently at. When there are large changes to the layout of the database, this
     * number is bumped and all databases with older versions will be dumped and replaced with a new index database.
     *
     * @var int
     */
    const DATABASE_VERSION = 19;

    /**
     * @var IndexDatabase
     */
    protected $indexDatabase;

    /**
     * @var CachingIndexDataAdapter
     */
    protected $indexDataAdapter;

    /**
     * @var string
     */
    protected $databaseFile;

    /**
     * @var FilesystemCache
     */
    protected $filesystemCache;

    /**
     * @inheritDoc
     */
    public function execute(array $arguments)
    {
        if (count($arguments) < 1) {
            throw new UnexpectedValueException(
                'Not enough arguments passed. Usage: . <command> <database path> [<additional options>]'
            );
        }

        $optionCollection = new OptionCollection();
        $optionCollection->add('database:', 'The index database to use.' )->isa('string');

        $this->attachOptions($optionCollection);

        $processedArguments = null;
        $parser = new OptionParser($optionCollection);

        try {
            $processedArguments = $parser->parse($arguments);
        } catch(\Exception $e) {
            return $this->outputJson(false, $e->getMessage());
        }

        if (!isset($processedArguments['database'])) {
            return $this->outputJson(false, 'No database path passed!');
        }

        $this->databaseFile = $processedArguments['database']->value;

        $this->setIndexDatabase($this->createIndexDatabase($this->databaseFile));

        try {
            return $this->process($processedArguments);
        } catch (UnexpectedValueException $e) {
            return $this->outputJson(false, $e->getMessage());
        }
    }

    /**
     * Creates an index database instance for the database on the specified path.
     *
     * @param string $filePath
     *
     * @return IndexDatabase
     */
    protected function createIndexDatabase($filePath)
    {
        return new IndexDatabase($filePath, static::DATABASE_VERSION);
    }

    /**
     * Sets the indexDatabase to use.
     *
     * @param IndexDatabase $indexDatabase
     *
     * @return $this
     */
    public function setIndexDatabase(IndexDatabase $indexDatabase)
    {
        $this->indexDatabase = $indexDatabase;
        return $this;
    }

    /**
     * Sets up command line arguments expected by the command.
     *
     * Operates as a(n optional) template method.
     *
     * @param OptionCollection $optionCollection
     */
    protected function attachOptions(OptionCollection $optionCollection)
    {

    }

    /**
     * Executes the actual command and processes the specified arguments.
     *
     * Operates as a template method.
     *
     * @param ArrayAccess $arguments
     *
     * @return string Output to pass back.
     */
    abstract protected function process(ArrayAccess $arguments);

    /**
     * @param string|null $file
     * @param bool        $isStdin
     *
     * @throws LogicException
     * @throws UnexpectedValueException
     */
    protected function getSourceCode($file, $isStdin)
    {
        $code = null;

        if ($isStdin) {
            // NOTE: This call is blocking if there is no input!
            return file_get_contents('php://stdin');
        } else {
            if (!$file) {
                throw new UnexpectedValueException('The specified file does not exist!');
            }

            return @file_get_contents($file);
        }

        throw new LogicException('Should never be reached.');
    }

    /**
     * Calculates the 1-indexed line the specified byte offset is located at.
     *
     * @param string $source
     * @param int    $offset
     *
     * @return int
     */
    protected function calculateLineByOffset($source, $offset)
    {
        return substr_count($source, "\n", 0, $offset) + 1;
    }

    /**
     * @return CachingIndexDataAdapter
     */
    protected function getIndexDataAdapter()
    {
        if (!$this->indexDataAdapter) {
            $this->indexDataAdapter = new CachingIndexDataAdapter(
                $this->indexDatabase,
                $this->getFilesystemCache()
            );
        }

        return $this->indexDataAdapter;
    }

    /**
     * Retrieves an instance of FilesystemCache. The object will only be created once if needed.
     *
     * @return FilesystemCache
     */
    protected function getFilesystemCache()
    {
        if (!$this->filesystemCache instanceof FilesystemCache) {
            $this->filesystemCache = new FilesystemCache(
                sys_get_temp_dir() . '/php-integrator-base/' . md5($this->databaseFile) . '/'
            );
        }

        return $this->filesystemCache;
    }

    /**
     * Outputs JSON.
     *
     * @param bool  $success
     * @param mixed $data
     *
     * @return string
     */
    protected function outputJson($success, $data)
    {
        return json_encode([
            'success' => $success,
            'result'  => $data
        ]);
    }
}
