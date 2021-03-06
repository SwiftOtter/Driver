<?php
declare(strict_types=1);
/**
 * @by SwiftOtter, Inc. 2/8/20
 * @website https://swiftotter.com
 **/

namespace Driver\Engines\MySql\Transformation;

use Driver\Commands\CommandInterface;
use Driver\Engines\MySql\Sandbox\Connection as SandboxConnection;
use Driver\Engines\MySql\Transformation\Anonymize\Seed;
use Driver\Pipeline\Environment\EnvironmentInterface;
use Driver\Pipeline\Transport\Status;
use Driver\Pipeline\Transport\TransportInterface;
use Driver\System\Configuration;
use Driver\System\Logs\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class Reduce extends Command implements CommandInterface
{
    /** @var Configuration */
    private $configuration;

    /** @var SandboxConnection */
    private $sandbox;

    /** @var LoggerInterface */
    private $logger;

    /** @var array */
    private $properties;
    
    /** @var ConsoleOutput */
    private $output;

    public function __construct(
        Configuration $configuration,
        SandboxConnection $sandbox,
        LoggerInterface $logger,
        ConsoleOutput $output,
        array $properties = []
    ) {
        $this->configuration = $configuration;
        $this->sandbox = $sandbox;
        $this->logger = $logger;
        $this->properties = $properties;
        $this->output = $output;

        parent::__construct('mysql-transformation-reduce');
    }

    public function go(TransportInterface $transport, EnvironmentInterface $environment)
    {
        /** @var OutputInterface $output */
        $config = $this->configuration->getNode('reduce/tables');
        $transport->getLogger()->notice("Beginning reducing table rows from reduce.yaml.");
        $this->output->writeln("<comment>Beginning reducing table rows from reduce.yaml.</comment>");

        if (!is_array($config) || (isset($config['disabled']) &&  $config['disabled'] === true)) {
            return $transport->withStatus(new Status('mysql-transformation-reduce', 'skipped'));
        }

        foreach ($this->configuration->getNode('reduce/tables') as $tableName => $details) {
            $column = $details['column'];
            $statement = $details['statement'];

            $query = "DELETE FROM ${tableName} WHERE ${column} ${statement}";

            try {
                $this->sandbox->getConnection()->beginTransaction();
                $this->sandbox->getConnection()->query($query);
                $this->sandbox->getConnection()->commit();
            } catch (\Exception $ex) {
                $this->logger->error('An error occurred when running this query: ' . $query);
                $this->logger->error($ex->getMessage());
                $this->output->writeln('<error>An error occurred when running this query: ' . $query. $ex->getMessage() . '</error>');
            }
        }

        $transport->getLogger()->notice("Row reduction process complete.");
        $this->output->writeln("<info>Row reduction process complete.</info>");
        
        return $transport->withStatus(new Status('mysql-transformation-reduce', 'success'));
    }

    public function getProperties()
    {
        return $this->properties;
    }

}
