<?php

namespace EnMarche\MailerBundle\Command;

use EnMarche\MailerBundle\Template\Synchronization\Finder\MailClassFinder;
use EnMarche\MailerBundle\Template\Synchronization\Finder\TemplateFinder;
use EnMarche\MailerBundle\Template\Synchronization\SyncRequestDispatcher;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TemplateSynchronizeCommand extends Command implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected static $defaultName = 'mailer:template:sync';

    /** @var SymfonyStyle */
    private $io;
    /** @var SyncRequestDispatcher */
    private $syncRequestDispatcher;

    private $templatePaths;
    private $mailClassPaths;

    public function __construct(array $mailClassPaths, array $templatePaths, SyncRequestDispatcher $syncRequestDispatcher) {
        parent::__construct();

        $this->templatePaths = $templatePaths;
        $this->mailClassPaths = $mailClassPaths;
        $this->syncRequestDispatcher = $syncRequestDispatcher;
    }

    protected function configure()
    {
        $this
            ->setDescription('Synchronize mail\'s templates')
            ->addOption('force', 'f', InputOption::VALUE_NONE)
        ;
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->mailClassPaths = $this->filterPaths($this->mailClassPaths);
        $this->templatePaths = $this->filterPaths($this->templatePaths);

        if (!$this->logger) {
            $this->logger = new NullLogger();
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (empty($this->mailClassPaths)) {
            throw new \InvalidArgumentException('Mail class path cannot be empty');
        }

        if (empty($this->templatePaths)) {
            throw new \InvalidArgumentException('Template path cannot be empty');
        }

        $templateNames = iterator_to_array(MailClassFinder::find($this->mailClassPaths));

        $foundedTemplatePaths = $this->findTemplatePaths($templateNames);

        foreach ($foundedTemplatePaths as $templateName => $files) {
            if (\count($files) > 1) {
                $this->logger->warning(sprintf("Many files found with for the same template name \"%s\".\nThese files will be skipped.", $templateName), $files);
                continue;
            }

            if (\count($files[0]['classes']) > 1) {
                $this->logger->warning(sprintf("Many mail classes were found for the same template name \"%s\".\nThis template will be skipped.", $templateName), $files);
                continue;
            }

            $reflection = new \ReflectionClass($files[0]['classes'][0]);

            $this->syncRequestDispatcher->dispatchRequest($files[0]['file'], $reflection->getName(), $reflection->getDefaultProperties()['type']);
        }
    }

    private function filterPaths(array $paths): array
    {
        return array_filter($paths, function (string $path) {
            if (!is_dir($path)) {
                $this->io->warning(sprintf('Path %s removed, it\'s not a directory', $path));

                return false;
            }

            return true;
        });
    }

    private function findTemplatePaths(array $templateNames): array
    {
        $data = [];

        foreach (TemplateFinder::find($this->templatePaths, $templateNames) as $templateName => $templatePath) {
            if (!isset($data[$templateName])) {
                $data[$templateName] = [];
            }

            $data[$templateName][] = [
                'file' => $templatePath,
                'classes' => array_keys($templateNames, $templateName, true),
            ];
        }

        return $data;
    }
}
